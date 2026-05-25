<?php
$db=new mysqli('localhost','root','','kai_news_kigali');
if($db->connect_error){
    echo 'DBERR:'.$db->connect_error; exit(1);
}
$res=$db->query('SELECT id,thumbnail,inline_images FROM news ORDER BY id DESC LIMIT 5');
while($r=$res->fetch_assoc()){
    echo json_encode($r)."\n";
}
$res2=$db->query('SELECT id,news_id,image_path,caption FROM news_gallery ORDER BY id DESC LIMIT 10');
while($r=$res2->fetch_assoc()){
    echo json_encode($r)."\n";
}
?>
