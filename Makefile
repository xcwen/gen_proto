source := README.md \
          ChangeLog.md \
          composer.json \
          composer.lock \
          bootstrap.php \

.PHONY: all
all: clean build/gen_proto.phar 
	cp build/gen_proto.phar gen_proto 

p: clean build/gen_proto.phar 
	cp build/gen_proto.phar gen_proto 
	cp build/gen_proto.phar ~/ac-php/gen_proto
	~/ac-php/cp_to_elpa.sh

.PHONY: clean
clean:
	@echo "Cleaning executables ..."
	@rm -f ./build/gen_proto.phar
	@rm -f ./gen_proto
	@echo "Done!"

.PHONY: dist-clean
dist-clean:
	@echo "Cleaning old build files and vendor libraries ..."
	@rm -rf ./build
	@rm -rf ./vendor
	@echo "Done!"

.PHONY: install
install: gen_proto
	@echo "Sorry, you need to move gen_proto to /usr/bin/gen_proto or /usr/local/bin/gen_proto or any place you want manually."

build:
	@if [ ! -x build ]; \
	then \
		mkdir build; \
	fi

build/composer.phar: | build
	@echo "Installing composer ..."
	@curl -s http://getcomposer.org/installer | php -- --install-dir=build

vendor: composer.lock build/composer.phar
	@echo "Installing vendor libraries ..."
	@php build/composer.phar install
	@touch vendor/

build/gen_proto.phar: vendor $(source) | build
	@php -dphar.readonly=0 buildPHAR.php
	@chmod +x build/gen_proto.phar
