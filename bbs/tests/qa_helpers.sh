#!/bin/bash
# QA helper functions for forum admin testing
export PATH="/usr/bin:/bin:/usr/sbin:/sbin:/opt/homebrew/bin:$PATH"
BASE="https://forum.test"

# get_csrf <jar> <path> : GET a page with jar, print the csrf_token value found in a hidden input
get_csrf() {
  local jar="$1" path="$2"
  curl -k -s -b "$jar" "$BASE$path" \
    | grep -oE 'name="csrf_token"[^>]*value="[^"]*"' \
    | head -1 \
    | sed -E 's/.*value="([^"]*)".*/\1/'
}

# login <jar> <identifier> <password> : log in, save session cookie to jar
login() {
  local jar="$1" ident="$2" pass="$3"
  rm -f "$jar"
  local tok
  tok=$(curl -k -s -c "$jar" "$BASE/login.php" \
    | grep -oE 'name="csrf_token"[^>]*value="[^"]*"' \
    | head -1 | sed -E 's/.*value="([^"]*)".*/\1/')
  curl -k -s -c "$jar" -b "$jar" -o /dev/null \
    --data-urlencode "csrf_token=$tok" \
    --data-urlencode "identifier=$ident" \
    --data-urlencode "password=$pass" \
    "$BASE/login.php"
}
