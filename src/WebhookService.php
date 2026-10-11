<?php

declare(strict_types=1);
final class WebhookService {
    public function __construct(private Database $db) {}
    public function deliver(array $form,int $submissionId,array $payload): void {
        $url=trim((string)($form['webhook_url'] ?? '')); if($url==='') return;
        $body=json_encode(['event'=>'submission.created','form'=>['id'=>(int)$form['id'],'name'=>$form['name'],'slug'=>$form['slug']],'submission'=>['id'=>$submissionId,'data'=>$payload]],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $code=null;$resp='';$error='';
        if(function_exists('curl_init')){
            $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json','User-Agent: FormRelay/1.0'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false]);
            $resp=(string)curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $error=curl_error($ch); curl_close($ch);
        } else $error='cURL extension is unavailable';
        $this->db->exec('INSERT INTO webhook_logs(form_id,submission_id,url,status_code,response_body,error,attempts,delivered_at,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[
            $form['id'],$submissionId,$url,$code,mb_substr($resp,0,2000),$error,1,($code>=200&&$code<300)?now_iso():null,now_iso()
        ]);
    }
}
