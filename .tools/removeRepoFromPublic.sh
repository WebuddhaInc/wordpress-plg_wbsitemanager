#!/bin/bash

# Testing

  # echo $(pwd)
  # echo $( cd $( dirname "${BASH_SOURCE[0]}" ) && pwd )
  # exit

# Joomla Path

  JPATH="$1"
  if [ ! -d "$JPATH" ]; then
    if [ -d "../../public_html/wp-admin" ] && [ -d "../../public_html/wp-content/plugins" ]; then
      JPATH="../../public_html/"
    else
      echo "Invalid Joomla Path";
    fi
  fi

  if [[ ! $JPATH =~ [\/$] ]]; then
    JPATH="$JPATH/"
  fi

# Repository Path

  RPATH="$2"
  if [ ! -d "$RPATH" ]; then
    if [ -d "./plugin" ]; then
      RPATH="./"
    else
      echo "Invalid Repo Path";
    fi
  fi

  if [[ ! $RPATH =~ [\/$] ]]; then
    RPATH="$RPATH/"
  fi

# Absolute Path

  ARPATH=$( cd $RPATH && pwd )/
  AJPATH=$( cd $JPATH && pwd )/

# Echo

  echo "Repository path:"
  echo "$ARPATH"
  echo ""

  echo "Wordpress path:"
  echo "$AJPATH"
  echo ""

  read -p "Press any key to continue... " -n1 -s
  echo

# remove

  rm $AJPATH/wp-content/plugins/wbsitemanager

