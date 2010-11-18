package PandoraFMS::DataServer;
##########################################################################
# Pandora FMS Data Server.
# Pandora FMS. the Flexible Monitoring System. http://www.pandorafms.org
##########################################################################
# Copyright (c) 2005-2010 Artica Soluciones Tecnologicas S.L
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public License
# as published by the Free Software Foundation; version 2
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
##########################################################################

use strict;
use warnings;

use threads;
use threads::shared;
use Thread::Semaphore;

use Time::Local;
use XML::Simple;
use POSIX qw(setsid strftime);

# Default lib dir for RPM and DEB packages
use lib '/usr/lib/perl5';

use PandoraFMS::Tools;
use PandoraFMS::DB;
use PandoraFMS::Core;
use PandoraFMS::ProducerConsumerServer;

# Inherits from PandoraFMS::ProducerConsumerServer
our @ISA = qw(PandoraFMS::ProducerConsumerServer);

# Global variables
my @TaskQueue :shared;
my %PendingTasks :shared;
my $Sem :shared = Thread::Semaphore->new;
my $TaskSem :shared = Thread::Semaphore->new (0);
my $AgentSem :shared = Thread::Semaphore->new (1);
my $ModuleSem :shared = Thread::Semaphore->new (1);

########################################################################################
# Data Server class constructor.
########################################################################################
sub new ($$;$) {
	my ($class, $config, $dbh) = @_;

	return undef unless $config->{'dataserver'} == 1;

	# Call the constructor of the parent class
	my $self = $class->SUPER::new($config, 0, \&PandoraFMS::DataServer::data_producer, \&PandoraFMS::DataServer::data_consumer, $dbh);

    bless $self, $class;
    return $self;
}

###############################################################################
# Run.
###############################################################################
sub run ($) {
	my $self = shift;
	my $pa_config = $self->getConfig ();

	print_message ($pa_config, " [*] Starting Pandora FMS Data Server.", 1);
	$self->setNumThreads ($pa_config->{'dataserver_threads'});
	$self->SUPER::run (\@TaskQueue, \%PendingTasks, $Sem, $TaskSem);
}

###############################################################################
# Data producer.
###############################################################################
sub data_producer ($) {
	my $self = shift;
	my $pa_config = $self->getConfig ();

	my @tasks;
	my @files;

	# Open the incoming directory
	opendir (DIR, $pa_config->{'incomingdir'})
	        || die "[FATAL] Cannot open Incoming data directory at " . $pa_config->{'incomingdir'} . ": $!";

	# Do not read more than max_queue_files files
 	my $file_count = 0;
 	while (my $file = readdir (DIR)) {
		if ($file_count > $pa_config->{"max_queue_files"}) {
			last;
		}
		push (@files, $file);
		$file_count++;
	}
	closedir(DIR);

	# Temporarily disable warnings (some files may have been deleted)
	{
		no warnings; 
		@files = sort { -C $pa_config->{'incomingdir'} . "/$b" <=> -C $pa_config->{'incomingdir'} . "/$a" } (@files);
	}

	# Queue files
	my $queue_count = 0;
 	foreach my $file_name (@files) {
		if ($queue_count > $pa_config->{"max_queue_files"}) {
			last;
		}

		# For backward compatibility
		if ($file_name =~ /^.*\.checksum$/) {
			unlink("$pa_config->{'incomingdir'}/$file_name");
			next;
		} 

		# Data files must have the extension .data
		next if ($file_name !~ /^.*\.data$/);

		$queue_count++;
		push (@tasks, $file_name);
	}

	return @tasks;
}

###############################################################################
# Data consumer.
###############################################################################
sub data_consumer ($$) {
	my ($self, $task) = @_;
	my ($pa_config, $dbh) = ($self->getConfig (), $self->getDBH ());

	my $file_name = $pa_config->{'incomingdir'};
	my $xml_err;
	
	# Fix path
	$file_name .= "/" unless (substr ($file_name, -1, 1) eq '/');	
	$file_name .= $task;

	# Double check that the file exists
	return unless (-f $file_name);

	# Try to parse the XML 2 times, with a delay between tries of 2 seconds
	my $xml_data;

	for (0..1) {
		eval {
			threads->yield;
			$xml_data = XMLin ($file_name, forcearray => 'module');
		};
	
		# Invalid XML
		if ($@) {
			$xml_err = $@;
			sleep (2);
			next;
		}

		# Ignore the timestamp in the XML and use the file timestamp instead
		$xml_data->{'timestamp'} = strftime ("%Y-%m-%d %H:%M:%S", localtime((stat($file_name))[9])) if ($pa_config->{'use_xml_timestamp'} eq '1' || ! defined ($xml_data->{'timestamp'}));

    	# Double check that the file exists
    	return unless (-f $file_name);

		unlink ($file_name);
		process_xml_data ($self->getConfig (), $file_name, $xml_data, $self->getServerID (), $self->getDBH ());
		return;	
	}

	rename($file_name, $file_name . '_BADXML');
	pandora_event ($pa_config, "Unable to process XML data file '$file_name': $xml_err", 0, 0, 0, 0, 0, 'error', 0, $dbh);
}

###############################################################################
# Process XML data coming from an agent.
###############################################################################
sub process_xml_data ($$$$$) {
	my ($pa_config, $file_name, $data, $server_id, $dbh) = @_;

	my ($agent_name, $agent_version, $timestamp, $interval, $os_version, $timezone_offset) =
		($data->{'agent_name'}, $data->{'version'}, $data->{'timestamp'},
		$data->{'interval'}, $data->{'os_version'}, $data->{'timezone_offset'});

	# Timezone offset must be an integer beween -12 and +12
	if (!defined($timezone_offset) || $timezone_offset !~ /[-+]?[0-9,11,12]/) {
		$timezone_offset = 0; # Default value
	}
	
	# Parent Agent Name
	my $parent_id = 0; # Default value for unknown parent
	my $parent_agent_name = $data->{'parent_agent_name'};
	if (defined ($parent_agent_name)) {
		$parent_id  =  get_agent_id ($dbh, $parent_agent_name);
		if ($parent_id < 1)	{ # Unknown parent
			$parent_id = 0;
		}
	logger($pa_config,"Parent_agent_name: $parent_agent_name parent_id: $parent_id",10);
	}

	my $valid_position_data = 1; 	

	# Get GIS information
	my ($longitude, $latitude, $altitude, $position_description) = (
		$data->{'longitude'}, $data->{'latitude'}, $data->{'altitude'}, 
		$data->{'position_description'});

	if ($pa_config->{'activate_gis'}) {

		# Validate the GIS informtation

		if (!defined($altitude) || $altitude !~ /[-+]?[0-9,11,12]/) {
                        $altitude = ''; # Default value
			# This could be a valid position data, not always will get altitude
                }
	
		if (!defined($longitude) || $longitude !~ /[-+]?[0-9,11,12]/) {
                        $longitude = ''; # Default value
			$valid_position_data = 0;
                }

		if (!defined($latitude) || $latitude !~ /[-+]?[0-9,11,12]/) {
                        $latitude = ''; # Default value
			$valid_position_data = 0;
                }

                if (!defined($position_description) ) { #FIXME: Validate the data with a regexp
                        $position_description = ''; # Default value
                }

		logger($pa_config, "Getting GIS Data=timezone_offset=$timezone_offset longitude=$longitude latitude=$latitude altitude=$altitude position_description=$position_description", 8);
	}
	# Unknown agent!
	if (! defined ($agent_name) || $agent_name eq '') {
		logger($pa_config, "$file_name has data from an unnamed agent", 3);
		return;
	}

	# Get current datetime from system if value AUTO is coming in the XML
	if ( $data->{'timestamp'} =~ /AUTO/ ){
		$timestamp = strftime ("%Y/%m/%d %H:%M:%S", localtime());
	}
	else {
		if ($timezone_offset != 0) {
		# Modify the timestamp with the timezone_offset
		logger($pa_config, "Unmodified timestamp = $timestamp", 5);
			$timestamp =~ /(\d+)[\/|\-](\d+)[\/|\-](\d+) +(\d+):(\d+):(\d+)/;
			logger($pa_config, "Unmodified timestamp = $1/$2/$3 $4:$5:$6", 5);
			my $utimestamp = ($timezone_offset * 3600); 
			eval {
				$utimestamp += timelocal($6, $5, $4, $3, $2 -1 , $1 - 1900);
			};
			if ($@) {
				logger($pa_config,"WARNING: Invalid timestamp ($@) using server timestamp.", 4);
				$timestamp = strftime ("%Y/%m/%d %H:%M:%S", localtime());
			}	
		logger($pa_config, "Seconds timestamp = $timestamp modified timestamp in seconds $utimestamp with timezone_offset = $timezone_offset", 5);
	$timestamp = strftime ("%Y-%m-%d %H:%M:%S", localtime($utimestamp));
		}
		logger($pa_config, "Modified timestamp = $timestamp with timezone_offset = $timezone_offset", 5);
	}

	
	# Check some variables
	$interval = 300 if (! defined ($interval) || $interval eq '');
	$os_version = 'N/A' if (! defined ($os_version) || $os_version eq '');
	
	# Get agent address from the XML if available
	my $address = '' ;
	$address = $data->{'address'} if (defined ($data->{'address'}));

	# Get agent id
	$AgentSem->down ();
	my $agent_id = get_agent_id ($dbh, $agent_name);
	if ($agent_id < 1) {
		if ($pa_config->{'autocreate'} == 0) {
			logger($pa_config, "ERROR: There is no agent defined with name $agent_name", 3);
			$AgentSem->up ();
			return;
		}
		
		# Get OS, group and description
		my $os = pandora_get_os ($data->{'os_name'});
		my $group_id = undef;
		$group_id = get_db_value ($dbh, 'SELECT id_grupo FROM tgrupo WHERE nombre = ?', $data->{'group'}) if (defined ($data->{'group'}));
		$group_id = $pa_config->{'autocreate_group'} unless defined ($group_id);
		my $description = '';
		$description = $data->{'description'} if (defined ($data->{'description'}));

		# Create the agent
		if ($valid_position_data == 1 && $pa_config->{'activate_gis'} != 0 ) {
			logger($pa_config, "Creating agent $agent_name at long: $longitude lat: $latitude alt: $altitude", 5);
			$agent_id = pandora_create_agent($pa_config, $pa_config->{'servername'}, $agent_name, $address, $group_id, $parent_id, $os, 
												 $description, $interval, $dbh, $timezone_offset, $longitude, $latitude, $altitude, $position_description);
		}
		else { # Ignore agent positional data
			logger($pa_config, "Creating agent $agent_name", 5);
			$agent_id = pandora_create_agent($pa_config, $pa_config->{'servername'}, $agent_name, $address, $group_id, $parent_id, $os,
												 $description, $interval, $dbh, $timezone_offset);
		}
		if (! defined ($agent_id)) {
			$AgentSem->up ();
			return;
		}
	}
	$AgentSem->up ();

	# Check if agent is disabled and return if it's disabled. Disabled agents doesnt process data
	# in order to avoid not only events, also possible invalid data coming from agents.
	my $agent = get_db_single_row ($dbh, 'SELECT * FROM tagente WHERE id_agente = ?', $agent_id);
	if (!defined ($agent)) {
		logger($pa_config, "Error retrieving information for agent ID $agent_id",10);
		return;
	}
	return if ($agent->{'disabled'} == 1);
	
	# Do not overwrite agent parameters if the agent is in normal mode
	if ($agent->{'modo'} == 0) {;
		$interval = $agent->{'intervalo'};
		$os_version = $agent->{'os_version'};
		$agent_version = $agent->{'agent_version'};
		$timezone_offset = $agent->{'timezone_offset'};
	} else {
		# Update agent address if necessary
		if ($address ne '' && $address ne $agent->{'direccion'}) {
			pandora_update_agent_address ($pa_config, $agent_id, $agent_name, $address, $dbh);
		}
	}

	if ($valid_position_data == 1 && $pa_config->{'activate_gis'} != 0) {
		if (!defined($parent_agent_name)){
                        $parent_agent_name = "";
                }
		logger($pa_config,"Parent_agent_name $parent_agent_name",10);
		if ($pa_config->{'update_parent'} == 1 && $parent_id != 0) {
		logger($pa_config,"Parent_agent_name $parent_agent_name",10);
			logger($pa_config, "Updating agent $agent_name at long: $longitude lat: $latitude alt: $altitude parent_id: $parent_id", 5);
			# Update agent information including position information and the paret
			pandora_update_agent($pa_config, $timestamp, $agent_id, $os_version, $agent_version, $interval, $dbh, $timezone_offset, 
								$longitude, $latitude, $altitude, $position_description, $parent_id);
		}
		else {
			logger($pa_config, "Updating agent $agent_name at long: $longitude lat: $latitude alt: $altitude", 5);
			# Update agent information including position information
		}		pandora_update_agent($pa_config, $timestamp, $agent_id, $os_version, $agent_version, $interval, $dbh, $timezone_offset, $longitude, $latitude, $altitude, $position_description);
	}
	else {
		if ($pa_config->{'update_parent'} == 1 && $parent_id != 0) {
			logger($pa_config, "Updating agent $agent_name parent_id: $parent_id", 5);
			# Update agent information including the parent  without position information
			pandora_update_agent($pa_config, $timestamp, $agent_id, $os_version, $agent_version, $interval, $dbh, $timezone_offset, undef, undef, undef, undef, $parent_id);
		}
		else {
			logger($pa_config, "Updating agent $agent_name", 5);
			# Update agent information without position information ignoring the parent
			pandora_update_agent($pa_config, $timestamp, $agent_id, $os_version, $agent_version, $interval, $dbh, $timezone_offset);
		}
	}	
	pandora_module_keep_alive ($pa_config, $agent_id, $agent_name, $server_id, $dbh);

	# Process modules
	foreach my $module_data (@{$data->{'module'}}) {

		my $module_name = get_tag_value ($module_data, 'name', '');

		# Unnamed module
		next if ($module_name eq '');

		my $module_type = get_tag_value ($module_data, 'type', 'generic_data');

		# Single data
		if (! defined ($module_data->{'datalist'})) {
			my $data_timestamp = get_tag_value ($module_data, 'timestamp', $timestamp);
			process_module_data ($pa_config, $module_data, $server_id, $agent_name, $module_name, $module_type, $interval, $data_timestamp, $dbh);
			next;
		}

		# Data list
		foreach my $list (@{$module_data->{'datalist'}}) {
			
			# Empty list
			next unless defined ($list->{'data'});
						
			foreach my $data (@{$list->{'data'}}) {
				
				# No value
				next unless defined ($data->{'value'});
							
				$module_data->{'data'} = $data->{'value'};
				my $data_timestamp = get_tag_value ($module_data, 'timestamp', $timestamp);
				process_module_data ($pa_config, $module_data, $server_id, $agent_name, $module_name,
									 $module_type, $interval, $data_timestamp, $dbh);
			}
		}
	}

	# Process inventory modules
	enterprise_hook('process_inventory_data', [$pa_config, $data, $server_id, $agent_name,
							 $interval, $timestamp, $dbh]);
}

##########################################################################
# Process module data, creating module if necessary.
##########################################################################
sub process_module_data ($$$$$$$$$) {
	my ($pa_config, $data, $server_id, $agent_name,
		$module_name, $module_type, $interval, $timestamp,
		$dbh) = @_;

	# Get agent data
	my $agent = get_db_single_row ($dbh, 'SELECT * FROM tagente WHERE nombre = ?', safe_input($agent_name));
	if (! defined ($agent)) {
		logger($pa_config, "Invalid agent '$agent_name' for module '$module_name'.", 3);
		return;
	}

	# Get module parameters, matching column names in tagente_modulo
	my $module_conf;
	$module_conf->{'max'} = get_tag_value ($data, 'max', 0);
	$module_conf->{'min'} = get_tag_value ($data, 'min', 0);
	$module_conf->{'descripcion'} = get_tag_value ($data, 'description', '');
	$module_conf->{'post_process'} = get_tag_value ($data, 'post_process', 0);
	$module_conf->{'module_interval'} = get_tag_value ($data, 'module_interval', 1);
	
	# Calculate the module interval in seconds
	$module_conf->{'module_interval'} *= $interval;

	# Allow , as a decimal separator
	$module_conf->{'post_process'} =~ s/,/./;

	# Get module data or create it if it does not exist
	$ModuleSem->down ();
	my $module = get_db_single_row ($dbh, 'SELECT * FROM tagente_modulo WHERE id_agente = ? AND nombre = ?', $agent->{'id_agente'}, safe_input($module_name));
	if (! defined ($module)) {
		# Do not auto create modules
		if ($pa_config->{'autocreate'} ne '1') {
			logger($pa_config, "Module '$module_name' not found for agent '$agent_name' and module auto-creation disabled.", 10);
			$ModuleSem->up ();
			return;
		}

		# Is the agent learning?
		if ($agent->{'modo'} ne '1') {
			logger($pa_config, "Learning mode disabled. Skipping module '$module_name' agent '$agent_name'.", 10);
			$ModuleSem->up ();
			return;
		}

		# Get the module type
		my $module_id = get_module_id ($dbh, $module_type);
		if ($module_id <= 0) {
			logger($pa_config, "Invalid module type '$module_type' for module '$module_name' agent '$agent_name'.", 3);
			$ModuleSem->up ();
			return;
		}

		# Create the module
		pandora_create_module ($pa_config, $agent->{'id_agente'}, $module_id, $module_name,
			$module_conf->{'max'}, $module_conf->{'min'}, $module_conf->{'post_process'},
			$module_conf->{'descripcion'}, $module_conf->{'module_interval'}, $dbh);
		$module = get_db_single_row ($dbh, 'SELECT * FROM tagente_modulo WHERE id_agente = ? AND nombre = ?', $agent->{'id_agente'}, safe_input($module_name));
		if (! defined ($module)) {
			logger($pa_config, "Could not create module '$module_name' for agent '$agent_name'.", 3);
			$ModuleSem->up ();
			return;
		}
	} else {
		# Update module configuration if in learning mode
		if ($agent->{'modo'} eq '1') {
			update_module_configuration ($pa_config, $dbh, $module, $module_conf);
		}
	}

	$ModuleSem->up ();

	# Module disabled!
	if ($module->{'disabled'} eq '1') {
		logger($pa_config, "Skipping disabled module '$module_name' agent '$agent_name'.", 10);
		return;
	}

	# Parse the timestamp and process the module
	if ($timestamp !~ /(\d+)\/(\d+)\/(\d+) +(\d+):(\d+):(\d+)/ &&
		$timestamp !~ /(\d+)\-(\d+)\-(\d+) +(\d+):(\d+):(\d+)/) {
		logger($pa_config, "Invalid timestamp '$timestamp' from module '$module_name' agent '$agent_name'.", 3);
		return;
	}
	my $utimestamp;
	eval {
 		$utimestamp = timelocal($6, $5, $4, $3, $2 - 1, $1 - 1900);
	};
	if ($@) {
		logger($pa_config, "Invalid timestamp '$timestamp' from module '$module_name' agent '$agent_name'.", 3);
		return;
	}
	#my $value = get_tag_value ($data, 'data', '');		
	my $data_object = get_module_data($data, $module_type);
	my $extra_macros = get_macros_for_data($data, $module_type);
	
	pandora_process_module ($pa_config, $data_object, $agent, $module, $module_type, $timestamp, $utimestamp, $server_id, $dbh, $extra_macros);
}

##########################################################################
# Retrieve module data from the XML tree.
##########################################################################
sub get_module_data($$){
	my ($data, $module_type) = @_;	

	my %data_object;

	# Log4x modules hava extended information
	if ($module_type eq 'log4x') {
		foreach my $attr ('severity','message', 'stacktrace'){
			$data_object{$attr} = get_tag_value ($data, $attr, '');
		}
	} else {
		$data_object{'data'} = get_tag_value ($data, 'data', '');
	}

	return \%data_object;
}

##########################################################################
# Retrieve module data from the XML tree.
##########################################################################
sub get_macros_for_data($$){
	my ($data, $module_type) = @_;

	my %macros;

	if ($module_type eq 'log4x') {
		foreach my $attr ('severity','message', 'stacktrace') {
			$macros{'_' . $attr . '_'} = get_tag_value ($data, $attr, '');
		}
	}

	return \%macros;
}

##########################################################################
# Update module configuration in tagente_modulo if necessary.
##########################################################################
sub update_module_configuration ($$$$) {
	my ($pa_config, $dbh, $module, $module_conf) = @_;

	# Update if at least one of the configuration tokens has changed
	if ($module->{'min'} != $module_conf->{'min'} || $module->{'max'} != $module_conf->{'max'} ||
	    $module->{'descripcion'} ne $module_conf->{'descripcion'} || $module->{'post_process'} != $module_conf->{'post_process'} ||
	    $module->{'module_interval'} != $module_conf->{'module_interval'}) {
			logger($pa_config, "Updating configuration for module '" . $module->{'nombre'}	. "'.", 10);
			db_do ($dbh, 'UPDATE tagente_modulo SET min = ?, max = ?, descripcion = ?, post_process = ?, module_interval = ?
			              WHERE id_agente_modulo = ?', $module_conf->{'min'}, $module_conf->{'max'}, $module_conf->{'descripcion'},
			       $module_conf->{'post_process'}, $module_conf->{'module_interval'}, $module->{'id_agente_modulo'});
			return;
	}
}

1;
__END__
