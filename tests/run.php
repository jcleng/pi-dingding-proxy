<?php
require __DIR__ . '/../src/Support.php';
use PiDingding\Support;
function ok($x,$m){if(!$x) throw new Exception($m);}
$dir=sys_get_temp_dir().'/pdp_'.bin2hex(random_bytes(3)); mkdir($dir.'/a',0777,true);
file_put_contents($dir.'/projects.json', json_encode(['projects'=>[['id'=>'p1','name'=>'A','path'=>$dir.'/a']]]));
$s=new Support($dir.'/projects.json',$dir);
ok(count($s->projects())===1,'projects');
ok($s->project('A')['path']===$dir.'/a','project lookup');
file_put_contents($dir.'/x.jsonl', "{\"type\":\"session\",\"id\":\"abc\",\"cwd\":\"$dir/a\"}\n".json_encode(['type'=>'message','message'=>['role'=>'user','content'=>'hello']])."\n");
ok(count($s->sessions($dir.'/a'))===1,'sessions');
$cmd=$s->buildPiCommand('echo',['--x']); ok($cmd[0]==='echo' && in_array('--x',$cmd),'command');
echo "ok\n";
