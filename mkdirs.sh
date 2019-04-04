#!/bin/bash

# todo make this better / more robust

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
  mkdir "$x"
  printf "$x\n"
  for y in $(alphanum); do
    DIRS=$((DIRS+1))
    mkdir "$x/$y"
  done
done

echo "Dirs: $DIRS"
