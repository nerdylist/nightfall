#!/bin/bash
BASE="https://forum.test"; AJAR=/tmp/qa_admin.jar; DB=/Volumes/Crucial/SITES/forum/forum.db
getcsrf() { curl -k -s -b "$AJAR" "$BASE$1" | grep -oE 'name="csrf_token"[^>]*value="[^"]*"' | head -1 | sed -E 's/.*value="([^"]*)".*/\1/'; }

echo "### DELETE CHAT MESSAGE ###"
CID=$(sqlite3 "$DB" "INSERT INTO chat_messages(thread_id,author_id,text,created_at) VALUES(1,1,'QA chat msg',datetime('now')); SELECT last_insert_rowid();")
echo "inserted chat id=$CID -> exists: $(sqlite3 "$DB" "SELECT COUNT(*) FROM chat_messages WHERE id=$CID;")"
TOK=$(getcsrf "/admin/chat.php")
curl -k -s -b "$AJAR" -o /dev/null -w 'del_chat HTTP=%{http_code} redir=%{redirect_url}\n' \
  --data-urlencode "csrf_token=$TOK" --data-urlencode "action=delete_chat" --data-urlencode "id=$CID" \
  "$BASE/admin/chat.php"
echo "after delete -> count(must be 0): $(sqlite3 "$DB" "SELECT COUNT(*) FROM chat_messages WHERE id=$CID;")"

echo; echo "### DELETE REACTION ###"
RID=$(sqlite3 "$DB" "INSERT INTO reactions(post_id,user_id,emoji,created_at) VALUES(1,1,'party',datetime('now')); SELECT last_insert_rowid();")
echo "inserted reaction id=$RID -> exists: $(sqlite3 "$DB" "SELECT COUNT(*) FROM reactions WHERE id=$RID;")"
TOK=$(getcsrf "/admin/chat.php")
curl -k -s -b "$AJAR" -o /dev/null -w 'del_reaction HTTP=%{http_code} redir=%{redirect_url}\n' \
  --data-urlencode "csrf_token=$TOK" --data-urlencode "action=delete_reaction" --data-urlencode "id=$RID" \
  "$BASE/admin/chat.php"
echo "after delete -> count(must be 0): $(sqlite3 "$DB" "SELECT COUNT(*) FROM reactions WHERE id=$RID;")"
