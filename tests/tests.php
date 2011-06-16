<?php
include_once("../parser.class.php");
echo "<pre>";

// Connect to database
mysql_connect('localhost', 'user', 'password');
mysql_select_db('dbname');

// Load SQL parser
$parser = new DS_SQL_Parser();
$queries = array();

$queries[] = "SELECT articles.article_title, format_title AS f_title, categories.category_title AS c_title FROM articles INNER JOIN formats ON articles.article_format_id = formats.format_id INNER JOIN categories ON articles.article_cat_id = categories.category_id WHERE articles.article_active = 1 ORDER BY article_title LIMIT 5";
$queries[] = "SELECT * FROM articles INNER JOIN formats ON articles.article_format_id = formats.format_id INNER JOIN categories ON articles.article_cat_id = categories.category_id WHERE articles.article_active = 1 ORDER BY article_title LIMIT 5";
$queries[] = "SELECT ax.* FROM articles AS ax LEFT JOIN formats ON ax.article_format_id = formats.format_id WHERE (ax.article_active = 1 AND article_id > 0) LIMIT 0,2";
$queries[] = "SELECT COUNT(*) FROM articles AS ax LEFT JOIN formats ON ax.article_format_id = formats.format_id WHERE article_active = 1";
$queries[] = "SELECT article_id, COUNT(*) AS total FROM articles LEFT JOIN formats ON articles.article_format_id = formats.format_id WHERE articles.article_active = 1";
$queries[] = "SELECT articles.*, IF(articles.article_user_id=0,'Anonymous',users.user_name) AS username FROM articles LEFT JOIN users ON articles.article_user_id = users.user_id";
$queries[] = "SELECT SUM(article_user_id) AS total_sum FROM articles LEFT JOIN formats ON articles.article_format_id = formats.format_id WHERE article_active = 1";

// Test queries
foreach ($queries as $sql) {
	try {
		echo $sql . "\n";
		echo str_repeat("-", strlen($sql)) . "\n";
		$results = $parser->parse($sql);
		print_r($results);
	}
	catch (Exception $e) {
		echo "Exception: " . $e->getMessage();
		exit();
	}
}
?>