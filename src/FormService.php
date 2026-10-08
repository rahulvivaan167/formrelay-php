<?php

declare(strict_types=1);

final class FormService {
    public function __construct(private Database $db, private array $config) {}
    public function list(): array { return $this->db->all('SELECT f.*, (SELECT COUNT(*) FROM submissions s WHERE s.form_id=f.id) submissions_count FROM forms f ORDER BY f.id DESC'); }
    public function get(int $id): ?array { return $this->db->one('SELECT * FROM forms WHERE id=?',[$id]); }
    public function bySlug(string $slug): ?array { return $this->db->one('SELECT * FROM forms WHERE slug=?',[$slug]); }
    public function create(array $d): int {
        $name=trim((string)($d['name'] ?? 'Untitled form')) ?: 'Untitled form';
        $slug=preg_replace('/[^a-z0-9-]+/','-',strtolower(trim((string)($d['slug'] ?? $name)))) ?: 'form';
        $base=$slug; $i=1; while($this->bySlug($slug)) $slug=$base.'-'.$i++;
        $t=now_iso();
        $this->db->exec('INSERT INTO forms(name,slug,secret_key,success_url,allowed_domains,required_fields,honeypot_field,rate_limit,webhook_url,is_active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[
            $name,$slug,random_token(24),trim((string)($d['success_url'] ?? '')),
            json_encode($this->lines($d['allowed_domains'] ?? '')),json_encode($this->lines($d['required_fields'] ?? '')),
            trim((string)($d['honeypot_field'] ?? '_gotcha')) ?: '_gotcha',max(1,min(300,(int)($d['rate_limit'] ?? 30))),trim((string)($d['webhook_url'] ?? '')),1,$t,$t
        ]);
        return $this->db->id();
    }
    public function update(int $id,array $d): void {
        $this->db->exec('UPDATE forms SET name=?, success_url=?, allowed_domains=?, required_fields=?, honeypot_field=?, rate_limit=?, webhook_url=?, is_active=?, updated_at=? WHERE id=?',[
            trim((string)$d['name']),trim((string)($d['success_url'] ?? '')),json_encode($this->lines($d['allowed_domains'] ?? '')),json_encode($this->lines($d['required_fields'] ?? '')),
            trim((string)($d['honeypot_field'] ?? '_gotcha')) ?: '_gotcha',max(1,min(300,(int)($d['rate_limit'] ?? 30))),trim((string)($d['webhook_url'] ?? '')),isset($d['is_active'])?1:0,now_iso(),$id
        ]);
    }
    private function lines(mixed $v): array { if(is_array($v)) return array_values(array_filter(array_map('trim',$v))); return array_values(array_filter(array_map('trim',preg_split('/[\r\n,]+/',(string)$v) ?: []))); }
    public function delete(int $id): void { $this->db->exec('DELETE FROM forms WHERE id=?',[$id]); }
    public function submissions(int $formId, string $q='', string $status=''): array {
        $sql='SELECT * FROM submissions WHERE form_id=?'; $p=[$formId];
        if($q!==''){ $sql.=' AND payload LIKE ?'; $p[]='%'.$q.'%'; }
        if($status!==''){ $sql.=' AND status=?'; $p[]=$status; }
        $sql.=' ORDER BY id DESC LIMIT 500'; return $this->db->all($sql,$p);
    }
    public function submission(int $id): ?array { return $this->db->one('SELECT s.*, f.name form_name, f.slug form_slug FROM submissions s JOIN forms f ON f.id=s.form_id WHERE s.id=?',[$id]); }
    public function setStatus(int $id,string $status): void { if(in_array($status,['new','read','archived'],true)) $this->db->exec('UPDATE submissions SET status=? WHERE id=?',[$status,$id]); }
    public function deleteSubmission(int $id): void { $this->db->exec('DELETE FROM submissions WHERE id=?',[$id]); }

    public function accept(array $form,array $payload,string $ip,string $ua,string $referer): array {
        if(!(int)$form['is_active']) return ['ok'=>false,'code'=>410,'error'=>'Form is disabled'];
        $allowed=json_decode($form['allowed_domains'],true) ?: [];
        if($allowed){
            $host=parse_url($referer,PHP_URL_HOST) ?: '';
            $valid=false; foreach($allowed as $d){ if($host===$d || str_ends_with($host,'.'.$d)){$valid=true;break;} }
            if(!$valid) return ['ok'=>false,'code'=>403,'error'=>'Origin is not allowed'];
        }
        $hp=$form['honeypot_field']; if($hp && !empty($payload[$hp])) return ['ok'=>true,'spam'=>true]; unset($payload[$hp]);
        $required=json_decode($form['required_fields'],true) ?: [];
        $missing=[]; foreach($required as $f){ if(!isset($payload[$f]) || trim((string)$payload[$f])==='') $missing[]=$f; }
        if($missing) return ['ok'=>false,'code'=>422,'error'=>'Missing required fields','fields'=>$missing];
        $bucket=gmdate('YmdHi');
        $this->db->exec('INSERT INTO rate_limits(form_id,ip,bucket,hits) VALUES(?,?,?,1) ON CONFLICT(form_id,ip,bucket) DO UPDATE SET hits=hits+1',[$form['id'],$ip,$bucket]);
        $rl=$this->db->one('SELECT hits FROM rate_limits WHERE form_id=? AND ip=? AND bucket=?',[$form['id'],$ip,$bucket]);
        if((int)$rl['hits'] > (int)$form['rate_limit']) return ['ok'=>false,'code'=>429,'error'=>'Rate limit exceeded'];
        $clean=[]; foreach($payload as $k=>$v){ if(is_scalar($v)||$v===null) $clean[(string)$k]=mb_substr(trim((string)$v),0,10000); }
        $this->db->exec('INSERT INTO submissions(form_id,payload,ip,user_agent,referer,status,created_at) VALUES(?,?,?,?,?,?,?)',[
            $form['id'],json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$ip,mb_substr($ua,0,500),mb_substr($referer,0,1000),'new',now_iso()
        ]);
        return ['ok'=>true,'id'=>$this->db->id(),'payload'=>$clean];
    }
}
