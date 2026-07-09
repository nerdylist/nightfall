#!/usr/bin/env bash
# Auth smoke test for the forum (Phase 2). Self-cleaning.
# Usage: bash tests/auth_smoke.sh   (uses https://forum.test by default)
set -u
BASE="${BASE:-https://forum.test}"
DB="/Volumes/Crucial/SITES/forum/forum.db"
DIR="$(cd "$(dirname "$0")" && pwd)"
J="$DIR/.smoke.jar"
cleanup() {
  sqlite3 "$DB" "DELETE FROM users WHERE username LIKE 'smoke_%' OR email LIKE 'smoke_%';"
  rm -f "$J"
}
trap cleanup EXIT

csrf() { # $1 = path
  curl -sk -c "$J" -b "$J" "$BASE/$1" \
    | grep -o 'name="csrf_token" value="[^"]*"' | head -1 \
    | sed 's/.*value="//;s/"//'
}

echo "BASE=$BASE"
rm -f "$J"

# Register
T=$(csrf register.php)
echo -n "register -> "
curl -sk -b "$J" -c "$J" -o /dev/null -w '%{http_code} %{redirect_url}\n' \
  -d "csrf_token=$T" -d "username=smoke_u" -d "email=smoke_u@example.com" \
  -d "password=supersecret" -d "confirm=supersecret" "$BASE/register.php"
echo "db row -> $(sqlite3 "$DB" "SELECT id,username,role,status,length(password_hash) FROM users WHERE username='smoke_u';")"

# Logged-in check
B=$(curl -sk -b "$J" -c "$J" "$BASE/index.php")
echo "user-menu present: $(echo "$B" | grep -c 'user-menu-trigger')  register-btn (0 expected): $(echo "$B" | grep -c 'class=\"btn btn-primary\" href=\"register.php\"')"

# Logout
T=$(curl -sk -b "$J" -c "$J" "$BASE/index.php" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
echo -n "logout -> "
curl -sk -b "$J" -c "$J" -o /dev/null -w '%{http_code} %{redirect_url}\n' -d "csrf_token=$T" "$BASE/logout.php"

# Login by username and email
for ID in smoke_u smoke_u@example.com; do
  rm -f "$J"
  T=$(csrf login.php)
  echo -n "login $ID -> "
  curl -sk -b "$J" -c "$J" -o /dev/null -w '%{http_code} %{redirect_url}\n' \
    -d "csrf_token=$T" -d "identifier=$ID" -d "password=supersecret" "$BASE/login.php"
done

# settings.php guard (logged out)
rm -f "$J"
echo -n "settings.php logged-out -> "
curl -sk -o /dev/null -w '%{http_code} %{redirect_url}\n' "$BASE/settings.php"

# Error scan
echo -n "error-pattern scan (0 expected): "
H=0
for U in index.php login.php register.php settings.php "category.php?id=1"; do
  H=$((H + $(curl -sk "$BASE/$U" | grep -icE 'Warning|Notice|Fatal error|headers already sent|Cannot modify header')))
done
echo "$H"

echo "done (smoke users auto-removed)"
