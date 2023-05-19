<?php

namespace App\Controllers;

use App\Models\UrlModel;
use CodeIgniter\Controller;

class Redirect extends Controller
{
    // 20230412 조원영 단축 URL 리다이렉트
    public function index($shortUrl)
    {
        // 모델 생성
        $urlModel = new UrlModel();

        // 변수 정리
        $ip = $this->request->getIPAddress();

        // 단축 URL로 기존 URL 조회
        $longUrl = $urlModel->getLongUrl($shortUrl);

        if ($longUrl) {
            $urlModel->updateClickCnt($shortUrl, $ip);
            $rtn = $urlModel->getUrlInfo($shortUrl);
            $g_seq = !empty($rtn->G_SEQ)    ? $rtn->G_SEQ : 0; // 광고 시퀀스
            $m_seq = !empty($rtn->M_SEQ)    ? $rtn->M_SEQ : 0; // 마케터 시퀀스
            $u_seq = !empty($rtn->SEQ)      ? $rtn->SEQ : 0; // URL 시퀀스

            $g_info = $urlModel->getGoodsInfo($g_seq); // 광고 타입 조회
            $g_cate = !empty($g_info->G_CATE) ? $g_info->G_CATE : '';
            $g_type = !empty($g_info->G_TYPE) ? $g_info->G_TYPE : '';

            $params     = array(
                'g_seq'     => $g_seq,
                'm_seq'     => $m_seq,
                'u_seq'     => $u_seq,
                'ip'        => $ip
            );

            if ($g_cate === 'CPC'){
                if ($g_type === '클릭형') {
                    // 클릭 포인트 지급
                    $pointRtn = $urlModel->setUserPoint($params);
                    if ($pointRtn) {
                        return redirect()->to($longUrl);
                    } else {
                        return show_500();
                    }
                }elseif ('클릭체류형'){
                    return redirect()->to('https://www.kodeflo.com/campaign/cpc5.php?u_seq='.$u_seq.'&m_seq='.$m_seq.'&g_seq='.$g_seq);
                }
            }

/*            $key = "kodefloKey"; // 암호화에 사용될 키
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
            $e_g_seq = openssl_encrypt($g_seq, 'AES-256-CBC', $key, 0, $iv);
            $e_m_seq = openssl_encrypt($m_seq, 'AES-256-CBC', $key, 0, $iv);
            $e_u_seq = openssl_encrypt($u_seq, 'AES-256-CBC', $key, 0, $iv);
            // 암호화된 데이터와 IV 값을 저장
            $encryptedDataWithIV_g_seq = base64_encode($iv . $e_g_seq);
            $encryptedDataWithIV_m_seq = base64_encode($iv . $e_m_seq);
            $encryptedDataWithIV_u_seq = base64_encode($iv . $e_u_seq);*/

            if (strpos($longUrl,'?')){
                return redirect()->to($longUrl.'&jid='.$g_seq.'&uid='.$m_seq.'&rd=15'.'&at='.$u_seq); // 원래 URL로 리다이렉트
            }
            return redirect()->to($longUrl.'?jid='.$g_seq.'&uid='.$m_seq.'&rd=15'.'&at='.$m_seq); // 원래 URL로 리다이렉트
        } else {
            return show_404(); // 원래 URL이 없을 경우 404 에러 페이지 출력
        }
    }
}