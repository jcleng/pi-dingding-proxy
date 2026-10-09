<?php
namespace PiDingding;

final class Support {
    public function __construct(private string $projectsFile, private string $sessionDir) {}
    public function projects(): array {
        $data = is_file($this->projectsFile) ? json_decode((string)file_get_contents($this->projectsFile), true) : [];
        $items = $data['projects'] ?? (is_array($data) ? $data : []);
        return array_values(array_filter($items, fn($p) => is_array($p) && !empty($p['path']) && is_dir($p['path'])));
    }
    public function project(string $query): ?array {
        foreach ($this->projects() as $p) if ($p['name'] === $query || $p['id'] === $query || $p['path'] === $query) return $p;
        return null;
    }
    public function sessions(string $cwd): array {
        $root = rtrim($this->sessionDir, '/').'/'.trim(strtr($cwd, '/\\:', '___'), '_');
        $files = [];
        foreach (array_merge(glob($root.'/*.jsonl') ?: [], glob(rtrim($this->sessionDir, '/').'/*.jsonl') ?: []) as $file) { $s=$this->readSession($file); if (($s['cwd']??'')===$cwd || $s['cwd']==='') $files[]=$s; }
        if (!$files && is_dir($this->sessionDir)) foreach (array_merge(glob($this->sessionDir.'/*.jsonl') ?: [], glob($this->sessionDir.'/*/*.jsonl') ?: []) as $file) { $s=$this->readSession($file); if (($s['cwd']??'')===$cwd) $files[]=$s; }
        usort($files, fn($a,$b)=>strcmp($b['timestamp']??'',$a['timestamp']??'')); return $files;
    }
    private function readSession(string $file): array {
        $first = fgets($h=fopen($file,'r')); fclose($h); $x=json_decode((string)$first,true) ?: [];
        $title=$x['name']??$x['title']??'';
        if (!$title && is_file($file)) { foreach (array_slice(file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [], 0, 30) as $row) { $r=json_decode($row,true); $m=$r['message']??[]; if (($m['role']??'')==='user') { $c=$m['content']??''; $title=is_array($c)?$this->plainText($c):trim((string)$c); break; } } }
        $title=$this->plainText((string)$title); if (mb_strlen($title)>40) $title=mb_substr($title,0,40).'...';
        if ($title==='') $title='未命名会话';
        return ['id'=>$x['id']??basename($file,'.jsonl'),'title'=>$title ?: '未命名会话','cwd'=>$x['cwd']??'','timestamp'=>$x['timestamp']??'','file'=>$file];
    }
    private function plainText(mixed $value): string {
        if (is_array($value)) { $parts=[]; foreach ($value as $v) { $p=$this->plainText($v); if ($p!=='') $parts[]=$p; } return implode(' ', $parts); }
        $text=trim((string)$value); $text=preg_replace('/https?:\\/\\/\\S+/u','',$text); $text=preg_replace('/[\\x00-\\x1F\\x7F]+/u',' ',$text); $text=preg_replace('/[\\[\\]{}<>`*_#|\\\\\/]+/u',' ',$text); return trim(preg_replace('/\\s+/u',' ',$text));
    }
    public function history(string $file, int $limit=10): array {
        if (!is_file($file)) return []; $rows=file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: []; $out=[];
        foreach (array_slice($rows,-200) as $row) { $x=json_decode($row,true); $m=$x['message']??null; if (!is_array($m) || !isset($m['role'])) continue; $text=$this->messageText($m['content']??''); if ($text==='') continue; $out[]=['role'=>(string)$m['role'],'content'=>$text]; }
        return array_slice($out,-$limit);
    }
    public function messageText(mixed $content): string {
        if (is_string($content)) return $this->plainText($content);
        if (!is_array($content)) return '';
        $parts=[];
        foreach ($content as $block) {
            if (is_string($block)) { $p=$this->plainText($block); if($p!=='')$parts[]=$p; continue; }
            if (!is_array($block)) continue;
            $type=$block['type']??'';
            if ($type==='text') { $p=$this->plainText($block['text']??''); if($p!=='')$parts[]=$p; }
            elseif ($type==='image') { $parts[]='[图片]'; }
        }
        return trim(implode(' ', $parts));
    }
    public function buildPiCommand(string $bin, array $args=[]): array { return array_merge([$bin],$args); }
}
