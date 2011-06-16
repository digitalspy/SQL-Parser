<?php
include_once("Parser.php");
echo "<pre>";

$sql = "SELECT articles.article_title, formats.format_title AS f_title, categories.category_title AS c_title FROM articles INNER JOIN formats ON articles.article_format_id = formats.format_id INNER JOIN categories ON articles.article_cat_id = categories.category_id WHERE articles.article_active = 1 AND formats.format_id > 0 ORDER BY f_title";


$sql = "		SELECT post.postid, thread.forumid, post.threadid, 
		IF(post.userid=0,99999999,post.userid) AS userid, 
		IF(thread.postuserid=0,99999999,thread.postuserid) AS postuserid, post.title, post.pagetext, post.dateline 
		FROM post 
		INNER JOIN thread AS thread ON (thread.threadid = post.threadid) 
		INNER JOIN thread_last_updated ON (thread_last_updated.threadid = post.threadid) 
		WHERE thread_last_updated.timestamp >= ( SELECT ds_time FROM spy_forum.sph_counter WHERE counter_id = 7 ) AND post.postid < 46969846 AND post.visible > 0";
		
$parse = new SQL_Parser(null, "MySQL");
print_r($parse->parse($sql));