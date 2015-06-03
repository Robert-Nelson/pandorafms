#!/bin/bash
CODEHOME=/home/HOME/robert/src/pandorafms
CODEHOME_ENT=/home/HOME/robert/src/pandora_enterprise
PANDHOME_ENT=$CODEHOME_ENT
RPMHOME=/home/HOME/robert/rpmbuild
VERSION=$(grep 'my $pandora_version =' $CODEHOME/pandora_server/lib/PandoraFMS/Config.pm | awk '{print substr($4, 2, length($4) - 3)}')
BUILD=$(grep 'my $pandora_build =' $CODEHOME/pandora_server/lib/PandoraFMS/Config.pm | awk '{print substr($4, 2, length($4) - 3)}')
X86_64=`uname -m | grep x86_64`
CONSOLEHOME=$CODEHOME/pandora_console
CONSOLEHOME_ENT=$CODEHOME_ENT/pandora_console
