<?php
namespace PiDingding;
final class Proxy {
    private array $state=[];
    public function __construct(private Support $support, private string $pi='pi', private int $timeout=600) {}
    public function execute(string $prompt,string $cwd,?string $sessionFile=null): string {
        $args=['-p','--mode','text','--no-extensions','--no-mcp']; if ($sessionFile) $args=array_merge($args,['--session',$sessionFile]); $args[]=$prompt;
        $spec=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']]; $p=proc_open(array_merge([$this->pi],$args),$spec,$pipes,$cwd); if (!is_resource($p)) throw new \RuntimeException('无法启动 PI'); fclose($pipes[0]); stream_set_timeout($pipes[1],$this->timeout); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($p); if ($code!==0) throw new \RuntimeException(trim($err) ?: 'PI 执行失败'); return trim($out);
    }
    public function handle(string $user,string $text): string {
        $text=trim($text); $state=$this->state[$user]??['project'=>null,'session'=>null];
        if ($text==='/help') return "命令：/projects /project <名称|路径> /project new <名称> /sessions /session current /session <id> /history\n直接发送文本即可与 PI 连续对话。";
        if ($text==='/projects') { $rows=[]; foreach($this->support->projects() as $p) $rows[]='- '.$p['name'].' (`'.$p['path'].'`)'; return $rows?implode("\n",$rows):'暂无项目'; }
        if (str_starts_with($text,'/project ')) { $q=trim(substr($text,9)); if(str_starts_with($q,'new ')) { return '创建项目请先在服务器创建目录后使用 /project <路径>，以避免误创建目录。'; } $p=$this->support->project($q); if(!$p) return '项目不存在'; $state['project']=$p; $state['session']=null; $this->state[$user]=$state; return '已切换项目：'.$p['name']; }
        if (!$state['project']) return '请先使用 /projects 查看并用 /project <名称> 选择项目。';
        if ($text==='/sessions') { $ss=$this->support->sessions($state['project']['path']); if(!$ss)return'暂无会话'; return implode("\n",array_map(fn($s)=>'- '.$s['id'].' '.$s['timestamp'],$ss)); }
        if ($text==='/session current') return $state['session'] ? '当前会话：'.$state['session']['id'] : '当前未指定会话';
        if (str_starts_with($text,'/session ')) { $id=trim(substr($text,9)); foreach($this->support->sessions($state['project']['path']) as $s) if(str_starts_with($s['id'],$id)){ $state['session']=$s;$this->state[$user]=$state;return'已切换会话：'.$s['id']; } return'会话不存在'; }
        if ($text==='/history') return '历史记录请先选择会话。';
        $this->state[$user]=$state; return $this->execute($text,$state['project']['path'],$state['session']['file']??null);
    }
}
