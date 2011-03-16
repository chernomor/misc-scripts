#!/bin/bash

TMP=`mktemp` || exit -1

RCPT=$1
THRESHOLD=$2

if [ -z "$RCPT" ]; then
	echo "Syntax: $0 <jabber recipient> [<volume threshold (percents)> [<volume group>]]"
	exit -1
fi

/sbin/lvdisplay $3 | sed 's/,[0-9]\+%//' \
	| awk --assign threshold=$THRESHOLD '
		/LV Name/ {
			name=$3;
			next
		}

		/Allocated to snapshot/ {
			alloc=$4;
			if(alloc>threshold) {
				print "Allocated size of "name" is "alloc"%"
			}
		}
	' > $TMP

if [ -s $TMP ] ; then
	(
		echo "Too high allocation of snapshot(s):"
		cat $TMP
	) | sendxmpp $RCPT
fi

rm -f $TMP

