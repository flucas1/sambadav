#!/bin/sh
VERSION=0.6.1

curl --remote-name -L "https://github.com/1afa/sambadav/archive/v$VERSION.tar.gz"
tar xzf v$VERSION.tar.gz
cd "sambadav-$VERSION/src"
composer --optimize-autoloader install
cd ../..
mv sambadav-$VERSION sambadav
