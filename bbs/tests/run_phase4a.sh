#!/bin/bash
BASE="https://forum.test"; AJAR=/tmp/qa_admin.jar; DB=/Volumes/Crucial/SITES/forum/forum.db

getcsrf() { curl -k -s -b "$AJAR" "$BASE$1" | grep -oE 'name="csrf_token"[^>]*value="[^"]*"' | head -1 | sed -E 's/.*value="([^"]*)".*/\1/'; }

echo "=== CREATE 'QA Test Cat ZZZ' ==="
TOK=$(getcsrf "/admin/categories.php"); echo "csrf=${TOK:0:12}..."
curl -k -s -b "$AJAR" -o /dev/null -w 'create HTTP=%{http_code} redir=%{redirect_url}\n' \
  --data-urlencode "csrf_token=$TOK" --data-urlencode "action=create" \
  --data-urlencode "name=QA Test Cat ZZZ" --data-urlencode "description=qa" \
  --data-urlencode "icon=t" --data-urlencode "sort_order=99" "$BASE/admin/categories.php"
sqlite3 -header -column "$DB" "SELECT id,name,sort_order FROM categories WHERE name='QA Test Cat ZZZ';"
NEWID=$(sqlite3 "$DB" "SELECT id FROM categories WHERE name='QA Test Cat ZZZ';"); echo "NEWID=$NEWID"

echo; echo "=== EDIT cat $NEWID -> 'QA Test Cat ZZZ2', sort_order 5 ==="
TOK=$(getcsrf "/admin/category-edit.php?id=$NEWID")
curl -k -s -b "$AJAR" -o /dev/null -w 'edit HTTP=%{http_code} redir=%{redirect_url}\n' \
  --data-urlencode "csrf_token=$TOK" --data-urlencode "id=$NEWID" \
  --data-urlencode "name=QA Test Cat ZZZ2" --data-urlencode "sort_order=5" \
  --data-urlencode "icon=t" --data-urlencode "description=qa" "$BASE/admin/category-edit.php?id=$NEWID"
sqlite3 -header -column "$DB" "SELECT id,name,sort_order FROM categories WHERE id=$NEWID;"

echo; echo "=== DELETE-BLOCKED: category id=2 (has threads) ==="
echo "threads in cat2: $(sqlite3 "$DB" "SELECT COUNT(*) FROM threads WHERE category_id=2;")"
TOK=$(getcsrf "/admin/categories.php")
curl -k -s -b "$AJAR" -o /dev/null -w 'del-blocked HTTP=%{http_code} redir=%{redirect_url}\n' \
  --data-urlencode "csrf_token=$TOK" --data-urlencode "action=delete" --data-urlencode "id=2" "$BASE/admin/categories.php"
echo "cat2 still exists -> $(sqlite3 "$DB" "SELECT name FROM categories WHERE id=2;")"
curl -k -s -b "$AJAR" "$BASE/admin/categories.php" | grep -oiE 'admin-flash[^"]*"[^>]*>[^<]+' | head -2

echo; echo "=== DELETE empty QA cat $NEWID ==="
echo "BEFORE: $(sqlite3 "$DB" "SELECT name FROM categories WHERE id=$NEWID;")"
TOK=$(getcsrf "/admin/categories.php")
curl -k -s -b "$AJAR" -o /dev/null -w 'del HTTP=%{http_code} redir=%{redirect_url}\n' \
  --data-urlencode "csrf_token=$TOK" --data-urlencode "action=delete" --data-urlencode "id=$NEWID" "$BASE/admin/categories.php"
echo "AFTER count for NEWID: $(sqlite3 "$DB" "SELECT COUNT(*) FROM categories WHERE id=$NEWID;")"
