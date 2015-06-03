#WMIC
#
%define name        wmic
%define version	    4.0.0tp4.rbn
Summary:            Linux WMI client to do WMI querys using DCOM http://samba.org
Name:               %{name}
Version:            %{version}
Release:            1
License:            GPL2
Group:		    System/Management
Source0:            wmic-4.0.0tp4.rbn.tar.gz
BuildRoot:          %{_tmppath}/%{name}-%{version}-build
BuildArch: 	    i686
AutoReq:            1
Provides:           %{name}-%{version}

%description
Linux WMI client to do WMI querys. More information at SAMBA4 project at http://www.samba.org/

%prep
rm -rf $RPM_BUILD_ROOT

%setup

%install 
mkdir -p $RPM_BUILD_ROOT/usr/bin/
install -s wmic $RPM_BUILD_ROOT/usr/bin/

%clean
rm -rf $RPM_BUILD_ROOT

%files
%defattr(-, root, root)
/usr/bin/wmic
