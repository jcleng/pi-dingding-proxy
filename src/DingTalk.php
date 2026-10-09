<?php
namespace PiDingding;
final class DingTalk {
    public function __construct(private string $secret, private bool $verify=true) {}
    public function verify(array $headers): bool {
        if (!$this->verify) return true; $ts=$headers['timestamp']??$headers['Timestamp']??''; $sign=$headers['sign']??$headers['Sign']??'';
        if (!$ts || !$sign) return false; $expected=base64_encode(hash_hmac('sha256',$ts."\n".$this->secret,$this->secret,true)); return hash_equals($expected,$sign);
    }
    public function reply(string $url,string $text,string $title='PI Agent'): bool {
        if (!$url) return false; $payload=json_encode(['msgtype'=>'markdown','markdown'=>['title'=>$title,'text'=>$text]],JSON_UNESCAPED_UNICODE);
        $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>30]); curl_exec($ch); $ok=curl_getinfo($ch,CURLINFO_HTTP_CODE)<400; curl_close($ch); return $ok;
    }
}
