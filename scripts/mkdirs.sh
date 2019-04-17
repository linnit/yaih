#!/bin/bash

# todo - clean this up a bit?

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

alphanum() {
  for x in {a..z}; do
    printf "$x "
  done;
  
  for y in {A..Z}; do
    printf "$y "
  done;
  
  for z in {0..9}; do
    printf "$z ";
  done;
}

for x in $(alphanum); do
  DIRS=$((DIRS+1))
  for y in $(alphanum); do
    DIRS=$((DIRS+1))
    mkdir -v -p "$IMAGEDIR/$x/$y"
    mkdir -v -p "$THUMBDIR/$x/$y"
  done
done

echo "Created '$DIRS' directories"
