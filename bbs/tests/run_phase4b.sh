#!/bin/bash
BASE="https://forum.test"; AJAR=/tmp/qa_admin.jar; DB=/Volumes/Crucial/SITES/forum/forum.db
getcsrf() { curl -k -s -b "$AJAR" "$BASE$1" | grep -oE 'name="csrf_token"[^>]*value="[^"]*"' | head -1 | sed -E 's/.*value="([^"]*)".*/\1/'; }
post_users() { local tok=$(getcsrf "/admin/users.php"); curl -k -s -b "$AJAR" -o /dev/null -w "%{http_code} %{redirect_url}\n" --data-urlencode "csrf_token=$tok" "$@" "$BASE/admin/users.php"; }
flash() { curl -k -s -b "$AJAR" "$BASE/admin/users.php" | grep -oiE 'admin-flash[^"]*"[^>]*>[^<]+' | head -1; }
row() { sqlite3 -header -column "$DB" "SELECT id,display_name,role,status,reputation FROM users WHERE id=$1;"; }

echo "### EDIT user 1 -> display_name 'Devon QA', reputation 777 ###"
echo "BEFORE:"; row 1
TOK=$(getcsrf "/admin/user-edit.php?id=1")
curl -k -s -b "$AJAR" -o /dev/null -w 'edit HTTP=%{http_code}\n' \
  --data-urlencode "csrf_token=$TOK" --data-urlencode "id=1" \
  --data-urlencode "display_name=Devon QA" --data-urlencode "reputation=777" \
  --data-urlencode "role=user" --data-urlencode "status=active" --data-urlencode "bio=" \
  "$BASE/admin/user-edit.php?id=1"
echo "AFTER edit:"; row 1
echo "-- restore display_name='Devon Marsh', reputation=1842 --"
TOK=$(getcsrf "/admin/user-edit.php?id=1")
curl -k -s -b "$AJAR" -o /dev/null -w 'restore HTTP=%{http_code}\n' \
  --data-urlencode "csrf_token=$TOK" --data-urlencode "id=1" \
  --data-urlencode "display_name=Devon Marsh" --data-urlencode "reputation=1842" \
  --data-urlencode "role=user" --data-urlencode "status=active" --data-urlencode "bio=" \
  "$BASE/admin/user-edit.php?id=1"
echo "AFTER restore:"; row 1

echo; echo "### PROMOTE user 1 -> admin (toggle_role) ###"
post_users --data-urlencode "action=toggle_role" --data-urlencode "id=1"
echo "role now: $(sqlite3 "$DB" "SELECT role FROM users WHERE id=1;")"
echo "### DEMOTE user 1 -> user (toggle_role) ###"
post_users --data-urlencode "action=toggle_role" --data-urlencode "id=1"
echo "role now: $(sqlite3 "$DB" "SELECT role FROM users WHERE id=1;")"

echo; echo "### BAN user 1 (toggle_status) ###"
post_users --data-urlencode "action=toggle_status" --data-urlencode "id=1"
echo "status now: $(sqlite3 "$DB" "SELECT status FROM users WHERE id=1;")"
echo "### UNBAN user 1 ###"
post_users --data-urlencode "action=toggle_status" --data-urlencode "id=1"
echo "status now: $(sqlite3 "$DB" "SELECT status FROM users WHERE id=1;")"

echo; echo "### SELF-DEMOTE BLOCKED: admin id=9 toggle_role ###"
echo "role before: $(sqlite3 "$DB" "SELECT role FROM users WHERE id=9;")"
post_users --data-urlencode "action=toggle_role" --data-urlencode "id=9"
echo "role after (must be admin): $(sqlite3 "$DB" "SELECT role FROM users WHERE id=9;")"
echo "flash: $(flash)"

echo; echo "### SELF-DELETE BLOCKED: admin id=9 delete ###"
post_users --data-urlencode "action=delete" --data-urlencode "id=9"
echo "user9 exists count (must be 1): $(sqlite3 "$DB" "SELECT COUNT(*) FROM users WHERE id=9;")"
echo "flash: $(flash)"

echo; echo "### LAST-ADMIN path: promote id=1, demote id=1 (not last), confirm ###"
echo "admin count before promote: $(sqlite3 "$DB" "SELECT COUNT(*) FROM users WHERE role='admin';")"
post_users --data-urlencode "action=toggle_role" --data-urlencode "id=1"   # promote 1 -> admin
echo "id1 role (should be admin): $(sqlite3 "$DB" "SELECT role FROM users WHERE id=1;")"
echo "admin count now: $(sqlite3 "$DB" "SELECT COUNT(*) FROM users WHERE role='admin';")"
post_users --data-urlencode "action=toggle_role" --data-urlencode "id=1"   # demote 1 (not last admin) -> should succeed
echo "id1 role after demote (should be user): $(sqlite3 "$DB" "SELECT role FROM users WHERE id=1;")"
echo "admin count final: $(sqlite3 "$DB" "SELECT COUNT(*) FROM users WHERE role='admin';")"

echo; echo "### USER-DELETE-BLOCKED-IF-CONTENT: delete id=1 (authored content) ###"
echo "threads by user1: $(sqlite3 "$DB" "SELECT COUNT(*) FROM threads WHERE author_id=1;") posts by user1: $(sqlite3 "$DB" "SELECT COUNT(*) FROM posts WHERE author_id=1;")"
post_users --data-urlencode "action=delete" --data-urlencode "id=1"
echo "user1 exists count (must be 1): $(sqlite3 "$DB" "SELECT COUNT(*) FROM users WHERE id=1;")"
echo "flash: $(flash)"
