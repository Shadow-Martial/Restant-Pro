#!/bin/bash

# Make all Dokku setup scripts executable
# Run this script on the Dokku server after copying the files

chmod +x dokku-setup.sh
chmod +x dokku-app-config.sh
chmod +x dokku-ssl-setup.sh
chmod +x dokku-services-setup.sh

echo "All scripts are now executable"
echo "Available scripts:"
echo "  - dokku-setup.sh (Main setup)"
echo "  - dokku-app-config.sh (App configuration)"
echo "  - dokku-ssl-setup.sh (SSL management)"
echo "  - dokku-services-setup.sh (Service management)"
echo ""
echo "Run ./dokku-setup.sh to start the setup process"