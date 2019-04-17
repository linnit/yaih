#!/bin/bash

DIR=$(dirname "${BASH_SOURCE[0]}")

if [ ! -f $DIR/../../.env ]; then
    echo "../../.env doesn't exit. Have you set up .env"
    exit
fi

source $DIR/../../.env

if [ ! -d $IMAGEDIR ]; then
    echo "$IMAGEDIR doesn't exist. Have you set up .env"
    exit
fi

if [ ! -d $THUMBDIR ]; then
    echo "$THUMBDIR doesn't exist. Have you set up .env"
    exit
fi

DIRS=0

IMAGES=$(find $IMAGEDIR -type f)
for IMG in $IMAGES; do
    if [[ $(file -b $IMG) =~ ^'JPEG ' ]]; then
        ls -lah $IMG
        file -b $IMG
        #jpegtran -copy none -progressive  -optimize -outfile $IMG $IMG
        echo ""
    fi
done

