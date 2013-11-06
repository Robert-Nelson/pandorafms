#!/bin/bash
CODEHOME=/root/code/pandora/branches/pandora_4.0
CODEHOME_ENT=/root/code/artica/code
RPMHOME=/usr/src/packages

if [ ! -d $RPMHOME/RPMS ]; then
	mkdir -p $RPMHOME/RPMS || exit 1
fi

echo "Creating REDHAT RPM packages in $RPMHOME/RPMS"

# Console
rpmbuild -ba $CODEHOME/pandora_console/pandora_console.redhat.spec || exit 1

# Server
rpmbuild -ba $CODEHOME/pandora_server/pandora_server.redhat.spec || exit 1

# Unix agent
rpmbuild -ba $CODEHOME/pandora_agents/unix/pandora_agent.redhat.spec || exit 1

# Enterprise console
rpmbuild -ba $CODEHOME_ENT/pandora/branches/4.0/pandora_console/enterprise/pandora_console_enterprise.redhat.spec || exit 1

# Enterprise server
rpmbuild -ba $CODEHOME_ENT/pandora/branches/4.0/pandora_server/PandoraFMS-Enterprise/pandora_server_enterprise.redhat.spec || exit 1

exit 0
