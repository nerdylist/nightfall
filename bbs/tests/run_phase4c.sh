#!/bin/bash
BASE="https://forum.test"; AJAR=/tmp/qa_admin.jar; DB=/Volumes/Crucial/SITES/forum/forum.db
getcsrf() { curl -k -s -b "$AJAR" "$BASE$1" | grep -oE 'name="csrf_token"[^>]*value="[^"]*"' | head -1 | sed -E 's/.*value="([^"]*)".*/\1/'; }

echo "### EDIT thread id=1 ###"
echo "BEFORE:"; sqlite3 -header -column "$DB" "SELECT id,title,pinned,locked,category_id FROM threads WHERE id=1;"
OPID=$(curl -k -s -b "$AJAR" "$BASE/admin/thread-edit.php?id=1" | grep -oE 'name="op_post_id"[^>]*value="[^"]*"' | head -1 | sed -E 's/.*value="([^"]*)".*/\1/')
EXCERPT=$(sqlite3 "$DB" "SELECT COALESCE(excerpt,'') FROM threads WHERE id=1;")
CATID=$(sqlite3 "$DB" "SELECT category_id FROM threads WHERE id=1;")
OPBODY=$(sqlite3 "$DB" "SELECT COALESCE(body,'') FROM posts WHERE id=$OPID;")
echo "op_post_id=$OPID category_id=$CATID"
TOK=$(getcsrf "/admin/thread-edit.php?id=1")
# change title + set pinned (it's already 1; we keep it checked which == pinned=1). Original pinned was 1.
curl -k -s -b "$AJAR" -o /dev/null -w 'save HTTP=%{http_code} redir=%{redirect_url}\n' \
  --data-urlencode "csrf_token=$TOK" --data-urlencode "action=save_thread" \
  --data-urlencode "id=1" --data-urlencode "op_post_id=$OPID" \
  --data-urlencode "title=QA Edited Title" --data-urlencode "category_id=$CATID" \
  --data-urlencode "excerpt=$EXCERPT" --data-urlencode "op_body=$OPBODY" \
  --data-urlencode "pinned=on" \
  "$BASE/admin/thread-edit.php?id=1"
echo "AFTER edit:"; sqlite3 -header -column "$DB" "SELECT id,title,pinned,locked FROM threads WHERE id=1;"
echo "-- restore title + pinned=1 --"
TOK=$(getcsrf "/admin/thread-edit.php?id=1")
curl -k -s -b "$AJAR" -o /dev/null -w 'restore HTTP=%{http_code}\n' \
  --data-urlencode "csrf_token=$TOK" --data-urlencode "action=save_thread" \
  --data-urlencode "id=1" --data-urlencode "op_post_id=$OPID" \
  --data-urlencode "title=Welcome to Nexus v2.0 - what is new" --data-urlencode "category_id=$CATID" \
  --data-urlencode "excerpt=$EXCERPT" --data-urlencode "op_body=$OPBODY" \
  --data-urlencode "pinned=on" \
  "$BASE/admin/thread-edit.php?id=1"
echo "AFTER restore:"; sqlite3 -header -column "$DB" "SELECT id,title,pinned,locked FROM threads WHERE id=1;"

echo; echo "### CASCADE DELETE ###"
# Build a throwaway thread + post + chat + reaction via sqlite3
TID=$(sqlite3 "$DB" "INSERT INTO threads(category_id,author_id,title,excerpt,created_at) VALUES(1,1,'QA Cascade Thread','x',datetime('now')); SELECT last_insert_rowid();")
PID=$(sqlite3 "$DB" "INSERT INTO posts(thread_id,author_id,body,created_at) VALUES($TID,1,'QA cascade post',datetime('now')); SELECT last_insert_rowid();")
CID=$(sqlite3 "$DB" "INSERT INTO chat_messages(thread_id,author_id,text,created_at) VALUES($TID,1,'QA cascade chat',datetime('now')); SELECT last_insert_rowid();")
RID=$(sqlite3 "$DB" "INSERT INTO reactions(post_id,user_id,emoji,created_at) VALUES($PID,1,'thumb',datetime('now')); SELECT last_insert_rowid();")
echo "created TID=$TID PID=$PID CID=$CID RID=$RID"
echo "BEFORE counts:"
echo "  threads(id=$TID): $(sqlite3 "$DB" "SELECT COUNT(*) FROM threads WHERE id=$TID;")"
echo "  posts(thread=$TID): $(sqlite3 "$DB" "SELECT COUNT(*) FROM posts WHERE thread_id=$TID;")"
echo "  chat(thread=$TID): $(sqlite3 "$DB" "SELECT COUNT(*) FROM chat_messages WHERE thread_id=$TID;")"
echo "  reactions(post=$PID): $(sqlite3 "$DB" "SELECT COUNT(*) FROM reactions WHERE post_id=$PID;")"
TOK=$(getcsrf "/admin/threads.php")
curl -k -s -b "$AJAR" -o /dev/null -w 'cascade-del HTTP=%{http_code} redir=%{redirect_url}\n' \
  --data-urlencode "csrf_token=$TOK" --data-urlencode "action=delete" --data-urlencode "id=$TID" \
  "$BASE/admin/threads.php"
echo "AFTER counts:"
echo "  threads(id=$TID): $(sqlite3 "$DB" "SELECT COUNT(*) FROM threads WHERE id=$TID;")"
echo "  posts(thread=$TID): $(sqlite3 "$DB" "SELECT COUNT(*) FROM posts WHERE thread_id=$TID;")"
echo "  chat(thread=$TID): $(sqlite3 "$DB" "SELECT COUNT(*) FROM chat_messages WHERE thread_id=$TID;")"
echo "  reactions(post=$PID): $(sqlite3 "$DB" "SELECT COUNT(*) FROM reactions WHERE post_id=$PID;")"
