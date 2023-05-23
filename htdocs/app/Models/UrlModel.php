<?php

namespace App\Models;

use CodeIgniter\Model;

class UrlModel extends Model
{

    // 단축 URL로 기존 URL 조회
    public function getLongUrl($shortUrl)
    {
        if (!empty($shortUrl)) {
            $query = "SELECT
                    URL
                  FROM AD_URL
                    WHERE 1=1 
                    AND SHORTEN_URL LIKE '%{$shortUrl}'
        ";
            $result = $this->db->query($query);
            $data = $result->getRow();
            if (!empty($data)) {
                return $data->URL;
            } else {
                return false;
            }
        }
    }
    // 클릭수 업데이트
    public function updateClickCnt($shortUrl, $ip)
    {
        $query = "
                SELECT
                SEQ, G_SEQ, M_SEQ
                FROM AD_URL
                WHERE 1=1
                AND SHORTEN_URL LIKE '%{$shortUrl}'
        ";
        $result = $this->db->query($query);
        $data = $result->getRow();
        $urlSeq = !empty($data->SEQ)    ? $data->SEQ    : 0;
        $adSeq  = !empty($data->G_SEQ)  ? $data->G_SEQ  : 0;
        $mSeq   = !empty($data->M_SEQ)  ? $data->M_SEQ  : 0;

        // 동일 IP로 클릭수 어뷰징 방지
        $query = "
                SELECT
                COUNT(1) AS CNT
                FROM AD_URL_CLICK
                WHERE 1=1
                 AND URL_SEQ = {$urlSeq}
                 AND CREATED_IP = '{$ip}'
        ";
        $result = $this->db->query($query);
        $data = $result->getRow();
        if ($data->CNT > 0){
            return 0;
        }else{
            // url 클릭수 업데이트
            $query = "
                UPDATE AD_URL SET
                CLICK = CLICK + 1
                WHERE 1=1
                AND SHORTEN_URL LIKE '%{$shortUrl}'
        ";
            $this->db->query($query);

            // 클릭 로그 등록
            $query = "
                    INSERT INTO AD_URL_CLICK
                    (URL_SEQ, G_SEQ, M_SEQ, CREATED_IP)
                    VALUES
                    ({$urlSeq}, {$adSeq}, {$mSeq}, '{$ip}')
            ";
        }
        return $this->db->query($query);
    }

    // 단축 URL로 광고시퀀스, 마케터시퀀스 조회
    public function getUrlInfo($shortUrl)
    {
        if (!empty($shortUrl)) {
            $query = "SELECT
                        M_SEQ, G_SEQ, SEQ
                      FROM AD_URL
                      WHERE 1=1 
                      AND SHORTEN_URL LIKE '%{$shortUrl}'
        ";
            $result = $this->db->query($query);
            $data = $result->getRow();

            if (!empty($data)) {
                return $data;
            } else {
                return false;
            }
        }
    }

    public function getGoodsInfo($g_seq)
    {
        $query = "
                SELECT
                    G_CATE, G_TYPE
                FROM GOODS
                WHERE 1=1
                AND SEQ = {$g_seq}
        ";
        $result = $this->db->query($query);
        $data = $result->getRow();

        if (!empty($data)) {
            return $data;
        } else {
            return false;
        }
    }

    public function setUserPoint($params)
    {
        if (empty($params['g_seq']) or empty($params['u_seq']) or empty($params['m_seq'])){
            return false;
        }

        // 일일상한 확인
        $query = "
                SELECT
                C_MAXIMUM
                FROM GOODS
                WHERE 1=1
                AND SEQ = {$params['g_seq']}
        ";
        $result = $this->db->query($query);
        if (!$result){
            return false;
        }
        $data   = $result->getRow();
        $max    = !empty($data->C_MAXIMUM)  ?   $data->C_MAXIMUM    : 0;

        // 일일 상한이 있을 때
        if ($max != 0){
            $query = "
                    SELECT
                    COUNT(1) AS CNT
                    FROM PAY_AMOUNT_HISTORY
                    WHERE 1=1
                    AND AD_SEQ = {$params['g_seq']}
                    AND DATE_FORMAT(CREATED_AT, '%Y-%m-%d') = CURDATE()
            ";
            $result = $this->db->query($query);
            if (!$result){
                return false;
            }
            $data   = $result->getRow();
            $cnt    = !empty($data->CNT)  ?   $data->CNT    : 0;
            if ($cnt == $max){
                return false;
            }
        }

        // 해당 ip에서 추가된 URL로 접속한 이력이 있는지 체크
        $query = "
                SELECT
                    COUNT(1) AS CNT
                FROM PAY_AMOUNT_HISTORY
                WHERE 1=1
                AND AD_SEQ = {$params['g_seq']}
                AND CREATED_IP = '{$params['ip']}'
        ";
        $result = $this->db->query($query);
        if (!$result){
            return false;
        }
        $data = $result->getRow();
        $cnt = !empty($data->CNT) ? $data->CNT : 0; // 같은 IP로 해당 URL을 클릭한 횟수 체크
        
        // url 생성한 ip인지 체크
        $query = "
                SELECT 
                    CREATED_IP
                FROM AD_URL
                WHERE 1=1
                AND SEQ = {$params['u_seq']}
        ";
        $result = $this->db->query($query);
        if (!$result){
            return false;
        }
        $data = $result->getRow();
        $c_ip = !empty($data->CREATED_IP) ? $data->CREATED_IP : 0; // url을 생성한 ip (220.1414.5151.4)


        if ($cnt == 0 and $c_ip != $params['ip']) {
            // 기본 단가 조회
            $query = "
                SELECT 
                    C_UNIT_PRICE
                FROM GOODS
                WHERE 1=1
                AND SEQ = {$params['g_seq']}
        ";
            $result = $this->db->query($query);
            if (!$result){
                return false;
            }
            $data = $result->getRow();
            $price = !empty($data->C_UNIT_PRICE) ? $data->C_UNIT_PRICE : 0; // 기본단가

            // 회원 레벨 조회
            $query = "
                SELECT
                    USR_LV
                FROM MEMBERS
                WHERE 1=1
                AND SEQ = {$params['m_seq']}
        ";
            $result = $this->db->query($query);
            if (!$result){
                return false;
            }
            $data = $result->getRow();
            $usr_lv = !empty($data->USR_LV) ? $data->USR_LV : 0; // 회원 레벨 (1, 33, 99, 파트너)
            if ($usr_lv == '파트너') {
                $usr_lv = 'PARTNER';
            } else {
                $usr_lv = 'LV' . $usr_lv;
            }

            // 레벨별 광고 단가 조회
            $query = "
                SELECT
                    {$usr_lv}
                FROM PRICE_PER_LEVEL
                WHERE 1=1
                AND G_SEQ = '{$params['g_seq']}'
        ";
            $result = $this->db->query($query);
            if (!$result){
                return false;
            }
            $data = $result->getRow();
            $per_level = !empty($data->{$usr_lv}) ? $data->{$usr_lv} : 0;

            $point = $price * ($per_level / 100); // 기본 단가 X 마케터 별 단가

            // 마케터에게 포인트 지급
            $query = "
                UPDATE MEMBERS SET
                USR_POINT = USR_POINT + {$point}
                WHERE 1=1
                AND SEQ = '{$params['m_seq']}'
        ";
            $result = $this->db->query($query);
            if (!$result){
                return false;
            }

            // 광고비 사용내역
            $query = "
            INSERT INTO PAY_AMOUNT_HISTORY
            (MEM_SEQ , AD_SEQ, URL_SEQ, CONTENT, AMOUNT, MK_AMOUNT, CREATED_IP) 
            VALUES 
            ({$params['m_seq']}, {$params['g_seq']}, {$params['u_seq']}, '광고비소진', {$price}, {$point}, '{$params['ip']}')
        ";
            $result = $this->db->query($query);
            if (!$result){
                return false;
            }

            // 마케터 지급내역
            $query = "
                INSERT INTO POINT_HISTORY
                (MEM_SEQ, AD_SEQ, POINT, CREATED_AT, APPROVE_YN)
                VALUES 
                ({$params['m_seq']}, {$params['g_seq']}, {$point}, now(), 'Y')
        ";
            $result = $this->db->query($query);
            if (!$result){
                return false;
            }

            // 광고잔여액 차감
            $query = "
                    UPDATE GOODS SET
                    C_CASH_REMAIN = C_CASH_REMAIN-{$price}
                    WHERE 1=1
                    AND SEQ = {$params['g_seq']}
                    LIMIT 1
            ";
            $result = $this->db->query($query);
            if (!$result){
                return false;
            }
            return true;
        }else{
            return true;
        }
    }
}