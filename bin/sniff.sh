#!/usr/bin/env sh

canonicalize() {
	NAME="$1"
	if [ -f "$NAME" ]
	then
		DIR=$(dirname -- "$NAME")
		NAME=$(cd -P "$DIR" > /dev/null && pwd -P)/$(basename -- "$NAME")
	fi
	while [ -h "$NAME" ]; do
		DIR=$(dirname -- "$NAME")
		SYM=$(readlink "$NAME")
		NAME=$(cd "$DIR" > /dev/null && cd $(dirname -- "$SYM") > /dev/null && pwd)/$(basename -- "$SYM")
	done
	echo "$NAME"
}

CONSOLE=$(dirname -- "$(canonicalize "$0")")
APP=$(dirname "$CONSOLE")

exec php "$CONSOLE"/sniff.php "$@"
exit 0
