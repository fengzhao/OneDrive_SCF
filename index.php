<?php
include 'vendor/autoload.php';
include 'functions.php';
global $oauth;
global $config;
$oauth = [
    'onedrive_ver' => 0, // 0默认 (1个人 ， 2世纪互联 ， 还不会)
];
$config = [
    'sitename' => getenv('sitename'),
    'passfile' => getenv('passfile'),
    'refresh_token' => '',
];
//在环境变量添加：
/*
sitename       ：网站的名称，不添加会显示为‘请在环境变量添加sitename’
admin          ：管理密码，不添加时不显示登录页面
public_path    ：使用API长链接访问时，网盘里公开的路径，不设置时默认为'/'
private_path   ：使用私人域名访问时，网盘的路径（可以一样），不设置时默认为'/'
imgup_path     ：设置图床路径，不设置这个值时该目录内容会正常列文件出来，设置后只有上传界面
passfile       ：自定义密码文件的名字，可以是'.password'，也可以是'aaaa.txt'等等；
        　       密码是这个文件的内容，可以空格、可以中文；列目录时不会显示，只有知道密码才能查看或下载此文件。
t1,t2,t3,t4,t5,t6,t7：把refresh_token按128字节切开来放在环境变量，不想再出现ctrl+c、ctrl+v把token也贴到github的事了
*/

function main_handler($event, $context)
{
    global $oauth;
    global $config;
    $event = json_decode(json_encode($event), true);
    $context = json_decode(json_encode($context), true);
    $event1 = $event;
    if (strlen(json_encode($event1['body']))>150) $event1['body']=substr($event1['body'],0,strpos($event1['body'],'base64')+strlen('base64%2C')) . 'Too Long!...' . substr($event1['body'],-100);
    echo urldecode(json_encode($event1)) . '
 
' . urldecode(json_encode($context)) . '
 
';
    config_oauth();
    $function_name = $context['function_name'];
    $config['function_name'] = $function_name;
    $host_name = $event['headers']['host'];
    $serviceId = $event['requestContext']['serviceId'];
    if ( $serviceId === substr($host_name,0,strlen($serviceId)) ) {
        $config['base_path'] = '/'.$event['requestContext']['stage'].'/'.$function_name.'/';
        $config['list_path'] = getenv('public_path');
        $path = substr($event['path'], strlen('/'.$function_name));
    } else {
        $config['base_path'] = getenv('base_path');
        if (empty($config['base_path'])) $config['base_path'] = '/';
        $config['list_path'] = getenv('private_path');
        $path = substr($event['path'], strlen($event['requestContext']['path']));
    }
    if (substr($path,-1)=='/') $path=substr($path,0,-1);
    if (empty($config['list_path'])) {
        $config['list_path'] = '/';
    } else {
        $config['list_path'] = spurlencode($config['list_path'],'/') ;
    }
    if (empty($config['sitename'])) $config['sitename'] = '请在环境变量添加sitename';
    $config['imgup_path'] = getenv('imgup_path');
    $config['sourceIp'] = $event['requestContext']['sourceIp'];
    unset($files);
    unset($_POST);
    unset($_GET);
    unset($_COOKIE);
    $_GET = $event['queryString'];
    $_SERVER['PHP_SELF'] = path_format($config['base_path'] . $path);
    $referer = $event['headers']['referer'];
    $tmpurl = substr($referer,strpos($referer,'//')+2);
    $refererhost = substr($tmpurl,0,strpos($tmpurl,'/'));
    if ($refererhost==$host_name) {
        $config['current_url'] = substr($referer,0,strpos($referer,'//')) . '//' . $host_name.$_SERVER['PHP_SELF'];
    } else {
        $config['current_url'] = '';
    }
    $_POSTbody = explode("&",$event['body']);
    foreach ($_POSTbody as $postvalues){
        $pos = strpos($postvalues,"=");
        $_POST[urldecode(substr($postvalues,0,$pos))]=urldecode(substr($postvalues,$pos+1));
    }
    $cookiebody = explode("; ",$event['headers']['cookie']);
    foreach ($cookiebody as $cookievalues){
        $tmp=explode("=",$cookievalues);
        $_COOKIE[$tmp[0]]=$tmp[1];
    }

    if (!$config['base_path']) {
        return message('Missing env <code>base_path</code>');
    }
    if (!$config['refresh_token']) $config['refresh_token'] = getenv('t1').getenv('t2').getenv('t3').getenv('t4').getenv('t5').getenv('t6').getenv('t7');
    if (!$config['refresh_token']) {
        if (strpos($path, '/authorization_code') !== FALSE && isset($_GET['code'])) {
            return message(get_refresh_token($_GET['code']));
        }
        return message('
Please set a <code>refresh_token</code> in environments<br>
<a target="_blank" href="'. $oauth['oauth_url'] .'authorize?response_type=code&client_id='. $oauth['client_id'] .'&redirect_uri='. $oauth['redirect_uri'] .'">Get a refresh_token</a><br><br>
When redirected, replace <code>http://localhost</code> with current host', 'Error', 500);
    }
    if ($_COOKIE[$function_name]==md5(getenv('admin')) && getenv('admin')!='' ) {
        $config['admin']=1;
    } else {
        $config['admin']=0;
    }
    if ($_GET['admin']) {
        $url=$_SERVER['PHP_SELF'];
        if ($_GET['preview']) $url .= '?preview';
        if (getenv('admin')!='') {
            if ($_POST['password1']==getenv('admin')) return adminform($function_name,md5($_POST['password1']),$url);
            return adminform();
        } else {
            return output('', 302, false, [ 'Location' => $url ]);
        }
    }
    $config['ajax']=0;
    if ($event['headers']['x-requested-with']=='XMLHttpRequest') {
        $config['ajax']=1;
    }

    return list_files($path);
}

function config_oauth()
{
    global $oauth;
    if ($oauth['onedrive_ver']==0) {
        // 0默认
        $oauth['oauth_url'] = 'https://login.microsoftonline.com/common/oauth2/';
        $oauth['client_id'] = '298004f7-c751-4d56-aba3-b058c0154fd2';
        $oauth['client_secret'] = '-%5E%28%21BpF-l9%2Fz%23%5B%2B%2A5t%29alg%3B%5BV%40%3B%3B%29_%5D%3B%29%40j%23%5EE%3BT%28%26%5E4uD%3B%2A%26%3F%232%29%3EH%3F';
        $oauth['redirect_uri'] = 'http://localhost/authorization_code';
        $oauth['api_url'] = 'https://graph.microsoft.com/v1.0/me/drive/root';
    }
}

function get_refresh_token($code)
{
    global $oauth;
    $ret = json_decode(curl_request(
        $oauth['oauth_url'] . 'token',
        'client_id='. $oauth['client_id'] .'&client_secret='. $oauth['client_secret'] .'&grant_type=authorization_code&resource=https://graph.microsoft.com/&redirect_uri='. $oauth['redirect_uri'] .'&code=' . $code), true);
    if (isset($ret['refresh_token'])) {
        $tmptoken=$ret['refresh_token'];
        $str = 'split:<br>';
        for ($i=1;strlen($tmptoken)>0;$i++) {
            $str .= 't' . $i . ':<textarea readonly style="width: 95%;height: 45px">' . substr($tmptoken,0,128) . '</textarea>';
            $tmptoken=substr($tmptoken,128);
        }
        return '<table width=100%><tr><td width=50%>refresh_token:<textarea readonly style="width: 100%;height: 500px">' . $ret['refresh_token'] . '</textarea></td><td>' . $str . '</td></tr></table>';
    }
    return '<pre>' . json_encode($ret, JSON_PRETTY_PRINT) . '</pre>';
}

function fetch_files($path = '/')
{
    global $oauth;
    global $config;
    $path1 = path_format($path);
    $path = path_format($config['list_path'] . path_format($path));
    $cache = null;
    $cache = new \Doctrine\Common\Cache\FilesystemCache(sys_get_temp_dir(), '.qdrive');
    if (!($files = $cache->fetch('path_' . $path))) {

        // https://docs.microsoft.com/en-us/graph/api/driveitem-get?view=graph-rest-1.0
        // https://docs.microsoft.com/zh-cn/graph/api/driveitem-put-content?view=graph-rest-1.0&tabs=http
        // https://developer.microsoft.com/zh-cn/graph/graph-explorer

        $url = $oauth['api_url'];
        if ($path !== '/') {
                    $url .= ':' . $path;
                    if (substr($url,-1)=='/') $url=substr($url,0,-1);
                }
        $url .= '?expand=children(select=name,size,file,folder,parentReference,lastModifiedDateTime)';
        $files = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $config['access_token']]), true);
        // echo $path . '<br><pre>' . json_encode($files, JSON_PRETTY_PRINT) . '</pre>';

        if (isset($files['folder'])) {
            if ($files['folder']['childCount']>200) {
                // files num > 200 , then get nextlink
                $page = $_POST['pagenum']==''?1:$_POST['pagenum'];
                $files=fetch_files_children($files, $path, $page, $cache);
            } else {
                // files num < 200 , then cache
                $cache->save('path_' . $path, $files, 60);
            }
        }
    }
    return $files;
}

function fetch_files_children($files, $path, $page, $cache)
{
    global $oauth;
    global $config;
    $cachefilename = '.SCFcache_'.$config['function_name'];
    $maxpage = ceil($files['folder']['childCount']/200);

    if (!($files['children'] = $cache->fetch('files_' . $path . '_page_' . $page))) {
                    // 下载cache文件获取跳页链接
        $cachefile = fetch_files(path_format($path1 . '/' .$cachefilename));
        if ($cachefile['size']>0) {
            $pageinfo = curl_request($cachefile['@microsoft.graph.downloadUrl']);
                        //$cachefilesize = strlen($pageinfo);
            $pageinfo = json_decode($pageinfo,true);
                        //$rsize=$files['size']-$cachefile['size'];
                        //if ($pageinfo['size']==$files['size']) {
            for ($page4=1;$page4<$maxpage;$page4++) {
                $cache->save('nextlink_' . $path . '_page_' . $page4, $pageinfo['nextlink_' . $path . '_page_' . $page4], 60);
                $pageinfocache['nextlink_' . $path . '_page_' . $page4] = $pageinfo['nextlink_' . $path . '_page_' . $page4];
            }
                        //}
        }
        $pageinfochange=0;
        for ($page1=$page;$page1>=1;$page1--) {
            $page3=$page1-1;
            $url = $cache->fetch('nextlink_' . $path . '_page_' . $page3);
            if ($url == '') {
                            //echo $page3 .'not have url'. $url .'<br>' ;
                if ($page1==1) {
                    $url = $oauth['api_url'];
                    if ($path !== '/') {
                        $url .= ':' . $path;
                        if (substr($url,-1)=='/') $url=substr($url,0,-1);
                        $url .= ':/children?$select=name,size,file,folder,parentReference,lastModifiedDateTime';
                    } else {
                        $url .= '/children?$select=name,size,file,folder,parentReference,lastModifiedDateTime';
                    }
                    $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $config['access_token']]), true);
                               // echo $url . '<br><pre>' . json_encode($children, JSON_PRETTY_PRINT) . '</pre>';
                    $cache->save('files_' . $path . '_page_' . $page1, $children['value'], 60);
                    $nextlink=$cache->fetch('nextlink_' . $path . '_page_' . $page1);
                    if ($nextlink!=$children['@odata.nextLink']) {
                        $cache->save('nextlink_' . $path . '_page_' . $page1, $children['@odata.nextLink'], 60);
                        $pageinfocache['nextlink_' . $path . '_page_' . $page1] = $children['@odata.nextLink'];
                        $pageinfocache = clearbehindvalue($path,$page1,$maxpage,$pageinfocache);
                        $pageinfochange = 1;
                    }
                    $url = $children['@odata.nextLink'];
                    for ($page2=$page1+1;$page2<=$page;$page2++) {
                        sleep(1);
                        $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $config['access_token']]), true);
                                    //echo $page2 . ' ' . $url . '<br>';
                        $cache->save('files_' . $path . '_page_' . $page2, $children['value'], 60);
                        $nextlink=$cache->fetch('nextlink_' . $path . '_page_' . $page2);
                        if ($nextlink!=$children['@odata.nextLink']) {
                            $cache->save('nextlink_' . $path . '_page_' . $page2, $children['@odata.nextLink'], 60);
                            $pageinfocache['nextlink_' . $path . '_page_' . $page2] = $children['@odata.nextLink'];
                            $pageinfocache = clearbehindvalue($path,$page2,$maxpage,$pageinfocache);
                            $pageinfochange = 1;
                        }
                        $url = $children['@odata.nextLink'];
                    }
                                //echo $url . '<br><pre>' . json_encode($children, JSON_PRETTY_PRINT) . '</pre>';
                    $files['children'] = $children['value'];
                    $files['folder']['page']=$page;
                    $pageinfocache['filenum'] = $files['folder']['childCount'];
                    $pageinfocache['dirsize'] = $files['size'];
                    $pageinfocache['cachesize'] = $cachefile['size'];
                    $pageinfocache['size'] = $files['size']-$cachefile['size'];
                    if ($pageinfochange == 1) echo MSAPI('PUT', path_format($path.'/'.$cachefilename), json_encode($pageinfocache, JSON_PRETTY_PRINT), $config['access_token']);
                    return $files;
                }
            } else {
                            //echo $page3 .'have url<br> '. $url .'<br> ' ;
                for ($page2=$page3+1;$page2<=$page;$page2++) {
                    sleep(1);
                    $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $config['access_token']]), true);
                                //echo $page2 . ' ' . $url . '<br>';
                    $cache->save('files_' . $path . '_page_' . $page2, $children['value'], 60);
                    $nextlink=$cache->fetch('nextlink_' . $path . '_page_' . $page2);
                    if ($nextlink!=$children['@odata.nextLink']) {
                        $cache->save('nextlink_' . $path . '_page_' . $page2, $children['@odata.nextLink'], 60);
                        $pageinfocache['nextlink_' . $path . '_page_' . $page2] = $children['@odata.nextLink'];
                        $pageinfocache = clearbehindvalue($path,$page2,$maxpage,$pageinfocache);
                        $pageinfochange = 1;
                    }
                    $url = $children['@odata.nextLink'];
                }
                                //echo $url . '<br><pre>' . json_encode($children, JSON_PRETTY_PRINT) . '</pre>';
                $files['children'] = $children['value'];
                $files['folder']['page']=$page;
                $pageinfocache['filenum'] = $files['folder']['childCount'];
                $pageinfocache['dirsize'] = $files['size'];
                $pageinfocache['cachesize'] = $cachefile['size'];
                $pageinfocache['size'] = $files['size']-$cachefile['size'];
                if ($pageinfochange == 1) echo MSAPI('PUT', path_format($path.'/'.$cachefilename), json_encode($pageinfocache, JSON_PRETTY_PRINT), $config['access_token']);
                return $files;
            }
        }
    } else {
        $files['folder']['page']=$page;
        for ($page4=1;$page4<=$maxpage;$page4++) {
            if (!($url = $cache->fetch('nextlink_' . $path . '_page_' . $page4))) {
                if ($files['folder'][$path.'_'.$page4]!='') $cache->save('nextlink_' . $path . '_page_' . $page4, $files['folder'][$path.'_'.$page4], 60);
            } else {
                $files['folder'][$path.'_'.$page4] = $url;
            }
        }
    }
    return $files;
}

function list_files($path)
{
    global $oauth;
    global $config;
    $is_preview = false;
    if ($_GET['preview']) $is_preview = true;
    $path = path_format($path);
    $cache = null;
    $cache = new \Doctrine\Common\Cache\FilesystemCache(sys_get_temp_dir(), '.qdrive');
    if (!($access_token = $cache->fetch('access_token'))) {
        $ret = json_decode(curl_request(
            $oauth['oauth_url'] . 'token',
            'client_id='. $oauth['client_id'] .'&client_secret='. $oauth['client_secret'] .'&grant_type=refresh_token&resource=https://graph.microsoft.com/&redirect_uri='. $oauth['redirect_uri'] .'&refresh_token=' . $config['refresh_token']
        ), true);
        if (!isset($ret['access_token'])) {
            error_log('failed to get access_token. response' . json_encode($ret));
            throw new Exception('failed to get access_token.');
        }
        $access_token = $ret['access_token'];
        $config['access_token'] = $access_token;
        $cache->save('access_token', $config['access_token'], $ret['expires_in'] - 60);
    }

    if ($config['ajax']&&$_POST['action']=='del_upload_cache'&&substr($_POST['filename'],0,4)=='.tmp') {
        $tmp = MSAPI('DELETE',path_format(path_format($config['list_path'] . path_format($path)) . '/' . spurlencode($_POST['filename']) ),'',$access_token);
        return output($tmp);
    } 
    if ($config['admin']) {
        $tmp = adminoperate($path);
        if ($tmp['statusCode'] == 403 || $tmp['statusCode'] == 200) {
            return $tmp;
        }
        if ($tmp['statusCode'] == 201) {
            $path1 = path_format($config['list_path'] . path_format($path));
            $cache->save('path_' . $path1, json_decode('{}',true), 1);
        }
    } else {
        if (path_format($config['list_path'].urldecode($path))==path_format($config['imgup_path'])&&$config['imgup_path']!='') {
            $html = guestupload($path);
            if ($html!='') return $html;
        }
    }
    if (path_format($config['list_path'].urldecode($path))==path_format($config['imgup_path'])&&$config['imgup_path']!=''&&!$config['admin']) {
        // 是图床目录且不是管理
        $files = json_decode('{"folder":{}}', true);
    } else {
        $files = fetch_files($path);
    }
    if (isset($files['file']) && !$is_preview) {
        // is file && not preview mode
        $ishidden=passhidden(substr($path,0,strrpos($path,'/')));
        //if ($config['admin'] or $ishidden<4) {
        if ($ishidden<4) {
            return output('', 302, false, [
                'Location' => $files['@microsoft.graph.downloadUrl']
            ]);
        }
    }
    // return '<pre>' . json_encode($files, JSON_PRETTY_PRINT) . '</pre>';
    return render_list($path, $files);
}

function output($body, $statusCode = 200, $isBase64Encoded = false, $headers = ['Content-Type' => 'text/html'])
{
    //$headers['Access-Control-Allow-Origin']='*';
    return [
        'isBase64Encoded' => $isBase64Encoded,
        'statusCode' => $statusCode,
        'headers' => $headers,
        'body' => $body
    ];
}

function message($message, $title = 'Message', $statusCode = 200)
{
    return output('<html><meta charset=utf-8><body><h1>' . $title . '</h1><p>' . $message . '</p></body></html>', $statusCode);
}

function adminform($name = '', $pass = '', $path = '')
{
    $statusCode = 401;
    $html = '<html><head><title>管理登录</title><meta charset=utf-8></head>';
    if ($name!='') {
        $html .= '<script type="text/javascript">
            var expd = new Date();
            expd.setTime(expd.getTime()+(1*60*60*1000));
            var expires = "expires="+expd.toGMTString();
            document.cookie="'.$name.'='.$pass.';"+expires;
            //path='.$path.';
            location.href=location.protocol + "//" + location.host + "'.$path.'";
</script>';
        $statusCode = 302;
    }
    $html .= '
    <body>
	<div>
	  <center><h4>输入管理密码</h4>
	  <form action="" method="post">
		  <div>
		    <label>密码</label>
		    <input name="password1" type="password"/>
		    <button type="submit">查看</button>
          </div>
	  </form>
      </center>
	</div>
';
    $html .= '</body></html>';
    return output($html,$statusCode);
}

function guestupload($path)
{
    global $config;
    $path1 = path_format($config['list_path'] . path_format($path));
    if (substr($path1,-1)=='/') $path1=substr($path1,0,-1);
    if ($_POST['guest_upload_filecontent']!=''&&$_POST['upload_filename']!='') {
        $data = substr($_POST['guest_upload_filecontent'],strpos($_POST['guest_upload_filecontent'],'base64')+strlen('base64,'));
        $data = base64_decode($data);
            // 重命名为MD5加后缀
        $filename = spurlencode($_POST['upload_filename']);
        $ext = strtolower(substr($filename, strrpos($filename, '.')));
        $tmpfilename = "tmp/".date("Ymd-His")."-".$filename;
        $tmpfile=fopen($tmpfilename,'wb');
        fwrite($tmpfile,$data);
        fclose($tmpfile);
        $filename = md5_file($tmpfilename) . $ext;
        if ($config['current_url']!='') $locationurl = $config['current_url'] . '/' . $filename . '?preview';
        $response=MSAPI('POST',path_format($path1 . '/' . $filename) . ':/createUploadSession','{"item": { "@microsoft.graph.conflictBehavior": "fail"  }}',$config['access_token']);
        $responsearry=json_decode($response,true);
        if (isset($responsearry['error'])) return message($responsearry['error']['message']. '<hr><a href="' . $locationurl .'">' . $filename . '</a><br><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $uploadurl=$responsearry['uploadUrl'];
        $result = MSAPI('PUT',$uploadurl,$data,$config['access_token']);
        echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        return output('', 302, false, [ 'Location' => $locationurl ]);
    }
}

function adminoperate($path)
{
    global $config;
    $path1 = path_format($config['list_path'] . path_format($path));
    if (substr($path1,-1)=='/') $path1=substr($path1,0,-1);
    $tmparr['statusCode'] = 0;
    if ($config['ajax']) {
        $fileinfo['name'] = $_POST['filename'];
        $fileinfo['size'] = $_POST['filesize'];
        $fileinfo['lastModified'] = $_POST['lastModified'];
        $filename = spurlencode( $fileinfo['name'] );
        $cachefilename = '.tmp_' . $fileinfo['lastModified'] . '_' . $fileinfo['size'] . '_' . $filename;
        $getoldupinfo=fetch_files(path_format($path . '/' . $cachefilename));
        //echo json_encode($getoldupinfo, JSON_PRETTY_PRINT);
        if (isset($getoldupinfo['file'])) {
            $getoldupinfo_j = curl_request($getoldupinfo['@microsoft.graph.downloadUrl']);
            $getoldupinfo = json_decode($getoldupinfo_j , true);
            if ($getoldupinfo['size']==$fileinfo['size'] && $getoldupinfo['lastModified']==$fileinfo['lastModified']) {
                $expirationDateTime = ISO_format( json_decode( curl_request($getoldupinfo['uploadUrl']), true)['expirationDateTime'] );
                if (time() < strtotime($expirationDateTime)+8*60*60) {
                    echo $expirationDateTime.'没过期。';
                    return output($getoldupinfo_j);
                } else echo $expirationDateTime.'过期';
            }
        }
        $response=MSAPI('POST',path_format($path1 . '/' . $filename) . ':/createUploadSession','{"item": { "@microsoft.graph.conflictBehavior": "fail"  }}',$config['access_token']);
        $responsearry = json_decode($response,true);
        if (isset($responsearry['error'])) return output($response);
        $fileinfo['uploadUrl'] = $responsearry['uploadUrl'];
        echo MSAPI('PUT', path_format($path1 . '/' . $cachefilename), json_encode($fileinfo, JSON_PRETTY_PRINT), $config['access_token']);
        return output($response);
    }
    if ($_POST['upload_filename']!='') {
        // 上传
        $filename = spurlencode($_POST['upload_filename']);
        $data = substr($_POST['upload_filecontent'],strpos($_POST['upload_filecontent'],'base64')+strlen('base64,'));
        $data = base64_decode($data);
        $response=MSAPI('POST',path_format($path1 . '/' . $filename) . ':/createUploadSession','{"item": { "@microsoft.graph.conflictBehavior": "rename"  }}',$config['access_token']);
        $responsearry = json_decode($response,true);
        if (isset($responsearry['error'])) return message($responsearry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $uploadurl=$responsearry['uploadUrl'];
                    /*$datasplit=$data;
                    while ($datasplit!='') {
                        $tmpdata=substr($datasplit,0,1024000);
                        $datasplit=substr($datasplit,1024000);
                        echo MSAPI('PUT',$uploadurl,$tmpdata,$config['access_token']);
                    }//大文件循环PUT，SCF用不上*/
        $result = MSAPI('PUT',$uploadurl,$data,$config['access_token']);
        echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $tmparr['statusCode'] = 201;
    }
    if ($_POST['rename_newname']!=$_POST['rename_oldname'] && $_POST['rename_newname']!='') {
        // 重命名
        $oldname = spurlencode($_POST['rename_oldname']);
        $oldname = path_format($path1 . '/' . $oldname);
        $data = '{"name":"' . $_POST['rename_newname'] . '"}';
                //echo $oldname;
        $result = MSAPI('PATCH',$oldname,$data,$config['access_token']);
        echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $tmparr['statusCode'] = 201;
    }
    if ($_POST['delete_name']!='') {
        // 删除
        $filename = spurlencode($_POST['delete_name']);
        $filename = path_format($path1 . '/' . $filename);
                //echo $filename;
        $result = MSAPI('DELETE', $filename, '', $config['access_token']);
        echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message'] . '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $tmparr['statusCode'] = 201;
    }
    if ($_POST['operate_action']=='加密') {
        // 加密
        if ($config['passfile']=='') return message('先在环境变量设置passfile才能加密','',403);
        if ($_POST['encrypt_folder']=='/') $_POST['encrypt_folder']=='';
        $foldername = spurlencode($_POST['encrypt_folder']);
        $foldername = path_format($path1 . '/' . $foldername . '/' . $config['passfile']);
                //echo $foldername;
        $result = MSAPI('PUT', $foldername, $_POST['encrypt_newpass'], $config['access_token']);
        echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $tmparr['statusCode'] = 201;
    }
    if ($_POST['move_folder']!='') {
        // 移动
        $moveable = 1;
        if ($path == '/' && $_POST['move_folder'] == '/../') $moveable=0;
        if ($_POST['move_folder'] == $_POST['move_name']) $moveable=0;
        if ($moveable) {
            $filename = spurlencode($_POST['move_name']);
            $filename = path_format($path1 . '/' . $filename);
                //echo $filename;
            $foldername = path_format('/'.urldecode($path1).'/'.$_POST['move_folder']);
            $data = '{"parentReference":{"path": "/drive/root:'.$foldername.'"}}';
                // echo $data;
            $result = MSAPI('PATCH', $filename, $data, $config['access_token']);
            echo $result;
            $resultarry = json_decode($result,true);
            if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
            $tmparr['statusCode'] = 201;
        }
    }
    if ($_POST['editfile']!='') {
        // 编辑
        $data = $_POST['editfile'];
        /*TXT一般不会超过4M，不用二段上传
        $filename = $path1 . ':/createUploadSession';
        $response=MSAPI('POST',$filename,'{"item": { "@microsoft.graph.conflictBehavior": "replace"  }}',$config['access_token']);
        $uploadurl=json_decode($response,true)['uploadUrl'];
        echo MSAPI('PUT',$uploadurl,$data,$config['access_token']);*/
        $result = MSAPI('PUT', $path1, $data, $config['access_token']);
        echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $tmparr['statusCode'] = 201;
    }
    if ($_POST['create_name']!='') {
        // 新建
        if ($_POST['create_type']=='file') {
            $filename = spurlencode($_POST['create_name']);
            $filename = path_format($path1 . '/' . $filename);
            $result = MSAPI('PUT', $filename, $_POST['create_text'], $config['access_token']);
            echo $result;
            $resultarry = json_decode($result,true);
            if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        }
        if ($_POST['create_type']=='folder') {
            $data = '{ "name": "' . $_POST['create_name'] . '",  "folder": { },  "@microsoft.graph.conflictBehavior": "rename" }';
            $result = MSAPI('POST', $path1 . ':/children', $data, $config['access_token']);
            echo $result;
            $resultarry = json_decode($result,true);
            if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        }
        $tmparr['statusCode'] = 201;
    }
    return $tmparr;
}

function MSAPI($method, $path, $data = '', $access_token)
{
    global $oauth;
    // 移目录，echo MSAPI('PATCH','/public/qqqq.txt','{"parentReference":{"path": "/drive/root:/public/release"}}',$access_token);
    // 改名，echo MSAPI('PATCH','/public/qqqq.txt','{"name":"f.txt"}',$access_token);
    // 删除，echo MSAPI('DELETE','/public/qqqq.txt','',$access_token);
    // echo $method. $path.$data;
    if (substr($path,0,7) == 'http://' or substr($path,0,8) == 'https://') {
        $url=$path;
        $lenth=strlen($data);
        $headers['Content-Length'] = $lenth;
        $lenth--;
        $headers['Content-Range'] = 'bytes 0-' . $lenth . '/' . $headers['Content-Length'];
    } else {
        $url = $oauth['api_url'];
        if ($path !== '/') {
            $url .= ':' . $path;
            if (substr($url,-1)=='/') $url=substr($url,0,-1);
        }
        if ($method=='PUT') {
            $url .= ':/content';
            $headers['Content-Type'] = 'text/plain';
        }
        if ($method=='PATCH') {
            $headers['Content-Type'] = 'application/json';
        }
        if ($method=='POST') {
            $headers['Content-Type'] = 'application/json';
        }
    }
    $headers['Authorization'] = 'Bearer ' . $access_token;
    if (!isset($headers['Accept'])) $headers['Accept'] = '*/*';
    if (!isset($headers['Referer'])) $headers['Referer'] = $url;
    $sendHeaders = array();
    foreach ($headers as $headerName => $headerVal) {
        $sendHeaders[] = $headerName . ': ' . $headerVal;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    /*if ($method=='PUT') {
        #curl_setopt($ch, CURLOPT_PUT, 1);
        #curl_setopt($ch, CURLOPT_INFILE, $data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,"PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
    }
    if ($method=='PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,"PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
    }*/
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);
    curl_setopt($ch, CURLOPT_POSTFIELDS,$data);

    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 返回获取的输出文本流
    curl_setopt($ch, CURLOPT_HEADER, 0);         // 将头文件的信息作为数据流输出
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $sendHeaders);
    $response = curl_exec($ch);
    curl_close($ch);
    echo '
';
    return $response;
}

function clearbehindvalue($path,$page1,$maxpage,$pageinfocache)
{
    for ($page=$page1+1;$page<$maxpage;$page++) {
        $pageinfocache['nextlink_' . $path . '_page_' . $page] = '';
    }
    return $pageinfocache;
}

function spurlencode($str,$splite='') {
    $str = str_replace(' ', '%20',$str);
    $tmp='';
    if ($splite!='') {
        $tmparr=explode($splite,$str);
        for($x=0;$x<count($tmparr);$x++) {
            if ($tmparr[$x]!='') $tmp .= $splite . urlencode($tmparr[$x]);
        }
    } else {
        $tmp = urlencode($str);
    }
    $tmp = str_replace('%2520', '%20',$tmp);
    return $tmp;
}

function encode_str_replace($str)
{
    $str = str_replace('&','&amp;',$str);
    $str = str_replace('+','%2B',$str);
    return $str;
}

function passhidden($path)
{
    global $config;
    $path = str_replace('+','%2B',$path);
    $path = str_replace('&amp;','&', path_format(urldecode($path)));
    if ($config['passfile'] != '') {
        if (substr($path,-1)=='/') $path=substr($path,0,-1);
        $hiddenpass=gethiddenpass($path,$config['passfile']);
        if ($hiddenpass != '') {
            return comppass($hiddenpass);
        } else {
            return 1;
        }
    } else {
        return 0;
    }
    return 4;
}

function gethiddenpass($path,$passfile)
{
    $ispassfile = fetch_files(spurlencode(path_format($path . '/' . $passfile),'/'));
    //echo $path . '<pre>' . json_encode($ispassfile, JSON_PRETTY_PRINT) . '</pre>';
    if (isset($ispassfile['file'])) {
        $passwordf=explode("\n",curl_request($ispassfile['@microsoft.graph.downloadUrl']));
        $password=$passwordf[0];
        $password=md5($password);
        return $password;
    } else {
        if ($path !== '' ) {
            $path = substr($path,0,strrpos($path,'/'));
            return gethiddenpass($path,$passfile);
        } else {
            return '';
        }
    }
    return '';
}

function comppass($pass) {
    if ($_POST['password1'] !== '') if (md5($_POST['password1']) === $pass ) return 2;    
    if ($_COOKIE['password'] !== '') if ($_COOKIE['password'] === $pass ) return 3;
    return 4;
}

function render_list($path, $files)
{
    global $config;
    @ob_start();
    date_default_timezone_set('Asia/Shanghai');
    $path = str_replace('%20','%2520',$path);
    $path = str_replace('+','%2B',$path);
    $path = str_replace('&','&amp;',path_format(urldecode($path))) ;
    $path = str_replace('%20',' ',$path);
    if ($path !== '/') {
        if (isset($files['file'])) {
            $pretitle = str_replace('&','&amp;', $files['name']);
        } else {
            $pretitle = $path;
        }
    } else {
      $pretitle = '首页';
    }
    $statusCode=200;
    ?>
    <!DOCTYPE html>
    <html lang="zh-cn">
    <head>
        <meta charset=utf-8>
        <meta http-equiv=X-UA-Compatible content="IE=edge">
        <meta name=viewport content="width=device-width,initial-scale=1">
        <link rel="icon" href="<?php echo $config['base_path'];?>favicon.ico" type="image/x-icon" />
        <link rel="shortcut icon" href="<?php echo $config['base_path'];?>favicon.ico" type="image/x-icon" />
        <title><?php echo $pretitle;?> - <?php echo $config['sitename'];?></title>
        <style type="text/css">
            body{font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:14px;line-height:1em;background-color:#f7f7f9;color:#000}
            a{color:#24292e;cursor:pointer;text-decoration:none}
            a:hover{color:#24292e}
            .title{text-align:center;margin-top:2rem;letter-spacing:2px;margin-bottom:2rem}
            .title a{color:#333;text-decoration:none}
            .list-wrapper{width:80%;margin:0 auto 40px;position:relative;box-shadow:0 0 32px 0 rgba(0,0,0,.1)}
            .list-container{position:relative;overflow:hidden}
            .list-header-container{position:relative}
            .list-header-container a.back-link{color:#000;display:inline-block;position:absolute;font-size:16px;margin:20px 10px;padding:10px 10px;vertical-align:middle;text-decoration:none}
            .list-container,.list-header-container,.list-wrapper,a.back-link:hover,body{color:#24292e}
            .list-header-container .table-header{margin:0;border:0 none;padding:30px 60px;text-align:left;font-weight:400;color:#000;background-color:#f7f7f9}
            .login{display: inline-table;position: absolute;font-size:16px;margin:30px 20px;vertical-align:middle;right:0px;top:0px}
            .list-body-container{position:relative;left:0;overflow-x:hidden;overflow-y:auto;box-sizing:border-box;background:#fff}
            .list-table{width:100%;padding:20px;border-spacing:0}
            .list-table tr{height:40px}
            .list-table tr[data-to]:hover{background:#f1f1f1}
            .list-table tr:first-child{background:#fff}
            .list-table td,.list-table th{padding:0 10px;text-align:left}
            .list-table .size,.list-table .updated_at{text-align:right}
            .list-table .file ion-icon{font-size:15px;margin-right:5px;vertical-align:bottom}
<?php if ($config['admin']) { ?>
            .operate{display: inline-table;margin:0 20px;list-style:none;}
            .operate ul{position: absolute;display: none;background: #fff;border:1px #f7f7f7 solid;border-radius: 5px;margin: -17px 0 0 -1px;padding: 0;color:#205D67;}
            .operate:hover ul{position: absolute;display:inline-table;}
            .operate ul li{padding:1px;list-style:none;}
            .operatediv_close{position: absolute;right: 3px;top:3px;}
<?php } ?>
            .readme{padding:8px;background-color: #fff;}
            #readme{padding: 20px;text-align: left}

            @media only screen and (max-width:480px){
                .title{margin-bottom: 24px}
                .list-wrapper{width:95%; margin-bottom:24px;}
                .list-table {padding: 8px}
                .list-table td, .list-table th{padding:0 10px;text-align:left;white-space:nowrap;overflow:auto;max-width:80px}
            }
        </style>
    </head>

    <body>
    <h1 class="title">
        <a href="<?php echo $config['base_path']; ?>"><?php echo $config['sitename'] ;?></a>
    </h1>
    
    <div class="list-wrapper">
        <div class="list-container">
            <div class="list-header-container">
                <?php if ($path !== '/') {
                    $current_url = $_SERVER['PHP_SELF'];
                    while (substr($current_url, -1) === '/') {
                        $current_url = substr($current_url, 0, -1);
                    }
                    if (strpos($current_url, '/') !== FALSE) {
                        $parent_url = substr($current_url, 0, strrpos($current_url, '/'));
                    } else {
                        $parent_url = $current_url;
                    }
                    ?>
                    <a href="<?php echo path_format($parent_url); ?>" class="back-link">
                        <ion-icon name="arrow-back"></ion-icon>
                    </a>
                <?php } ?>
                <h3 class="table-header"><?php echo str_replace('&','&amp;', $path); ?></h3>
                <div class="login">
                    <?php if (getenv('admin')!='') if (!$config['admin']) {?>
                    <a onclick="login();">登录</a>
                <?php } else { ?>
                        <li class="operate">管理<ul style="left:-15px">
                        <li><a onclick="logout()">登出</a></li>
                        <?php if (isset($files['folder'])) { ?>
                        <li><a onclick="showdiv(event,'create','');">新建</a></li>
                        <li><a onclick="showdiv(event,'encrypt','');">加密</a></li>
                        </ul></li>
                    <?php } 
                    } ?>
                </div>
            </div>
            <div class="list-body-container">
                <?php if (path_format($config['list_path'].$path)==path_format($config['imgup_path'])&&$config['imgup_path']!=''&&!$config['admin']) { ?>
                        <div id="upload_div" style="margin:10px"><center>
        <form action="" method="POST">
        <input id="upload_content" type="hidden" name="guest_upload_filecontent">
        <input id="upload_file" type="file" name="upload_filename" onchange="base64upfile()">
        <button type=submit>上传</button>
        文件大小<4M，不然传输失败！
        </form><center>
    </div>
                    <?php } else { 
                $ishidden=passhidden($path);
                //if ($config['admin'] or $ishidden<4) {
                if ($ishidden<4) {
                if (isset($files['file'])) {
                    ?>
                    <div style="margin: 12px 4px 4px; text-align: center">
                    	<div style="margin: 24px">
                            <textarea id="url" title="url" rows="1" style="width: 100%; margin-top: 2px;" readonly><?php echo path_format($config['base_path'] . '/' . $path); ?></textarea>
                            <a href="<?php echo path_format($config['base_path'] . '/' . $path);//$files['@microsoft.graph.downloadUrl'] ?>"><ion-icon name="download" style="line-height: 16px;vertical-align: middle;"></ion-icon>&nbsp;下载</a>
                        </div>
                        <div style="margin: 24px">
                        <?php
                        $ext = strtolower(substr($path, strrpos($path, '.') + 1));
                        if (in_array($ext, ['ico', 'bmp', 'gif', 'jpg', 'jpeg', 'jpe', 'jfif', 'tif', 'tiff', 'png', 'heic', 'webp'])) {
                            echo '
                        <img src="' . $files['@microsoft.graph.downloadUrl'] . '" alt="' . substr($path, strrpos($path, '/')) . '" style="width: 100%"/>
                        ';
                        } elseif (in_array($ext, ['mp4', 'webm', 'mkv', 'flv', 'blv', 'avi', 'wmv', 'ogg'])) {
                            echo '
                        <video src="' . $files['@microsoft.graph.downloadUrl'] . '" controls="controls" style="width: 100%"></video>
                        ';
                        } elseif (in_array($ext, ['mp3', 'wma', 'flac', 'wav'])) {
                            echo '
                        <audio src="' . $files['@microsoft.graph.downloadUrl'] . '" controls="controls" style="width: 100%"></audio>
                        ';
                        } /*elseif (in_array($ext, ['pdf'])) {
                            echo '
                        <embed src="' . $files['@microsoft.graph.downloadUrl'] . '" type="application/pdf" width="100%" height=800px">
                        ';
                        }*/ elseif (in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) {
                            echo '
                        <iframe id="office-a" src="https://view.officeapps.live.com/op/view.aspx?src=' . urlencode($files['@microsoft.graph.downloadUrl']) . '" style="width: 100%;height: 800px" frameborder="0"></iframe>
                        ';
                        } elseif (in_array($ext, ['txt', 'sh', 'php', 'asp', 'js', 'html'])) {
                            if ($files['name']==='当前demo的index.php') {
                                $txtstr = '<!--修改时间：' . date("Y-m-d H:i:s",filectime(__DIR__.'/index.php')) . '-->
';
                                $txtstr .= htmlspecialchars(file_get_contents(__DIR__.'/index.php'));
                            } else {
                                $txtstr = htmlspecialchars(curl_request($files['@microsoft.graph.downloadUrl']));
                            } ?>
                        <div id="txt">
                        <?php if ($config['admin']) { ?><form id="txt-form" action="" method="POST">
                            <a onclick="enableedit(this);" id="txt-editbutton">点击后编辑</a>
                            <a id="txt-save" style="display:none">保存</a>
                         <?php } ?>
                            <textarea id="txt-a" name="editfile" readonly style="width: 100%; margin-top: 2px;" <?php if ($config['admin']) echo 'onchange="document.getElementById(\'txt-save\').onclick=function(){document.getElementById(\'txt-form\').submit();}"';?> ><?php echo $txtstr;?></textarea>
                        <?php if ($config['admin']) echo '</form>';?>
                        </div>
                        <?php } elseif (in_array($ext, ['md'])) {
                            echo '
                        <div class="markdown-body" id="readme"><textarea id="readme-md" style="display:none;">' . curl_request($files['@microsoft.graph.downloadUrl']) . '</textarea></div>
                        ';
                        } else {
                            echo '<span>文件格式不支持预览</span>';
                        } ?>
                        </div>
                    </div>
          <?php } else { ?>
                    <table class="list-table">
                        <tr>
                            <!--<th class="updated_at" width="5%">序号</th>-->
                            <th class="file" width="60%">文件</th>
                            <th class="updated_at" width="25%">修改时间</th>
                            <th class="size" width="15%">大小</th>
                        </tr>
                        <!-- Dirs -->
                        <?php
                        $filenum = $_POST['filenum'];
                        if (!$filenum and $files['folder']['page']) $filenum = ($files['folder']['page']-1)*200;
                        $readme = false;
                        if (isset($files['error'])) {
                            echo '<tr><td colspan="3">' . $files['error']['message'] . '<td></tr>';
                            $statusCode=404;
                        } else {
                            #echo json_encode($files['children'], JSON_PRETTY_PRINT);
                            foreach ($files['children'] as $file) {
                                // Folders
                                if (isset($file['folder'])) { ?>
                                    <tr data-to>
                                        <!--<td class="updated_at"><?php $filenum++; echo $filenum;?></td>-->
                                        <td class="file">
                                            <ion-icon name="folder"></ion-icon>
                                            <a href="<?php echo path_format($config['base_path'] . '/' . $path . '/' . encode_str_replace($file['name'])); ?>">
                                                <?php echo str_replace('&','&amp;', $file['name']); ?>
                                            </a>
                                            <?php if ($config['admin']) {?>&nbsp;&nbsp;&nbsp;
                                            <li class="operate">管理<ul>
                                                <li><a onclick="showdiv(event,'encrypt','<?php echo str_replace('&','&amp;', $file['name']);?>');">加密</a></li>
                                                <li><a onclick="showdiv(event, 'rename','<?php echo str_replace('&','&amp;', $file['name']);?>');">重命名</a></li>
                                                <li><a onclick="showdiv(event, 'move','<?php echo str_replace('&','&amp;', $file['name']);?>');">移动</a></li>
                                                <li><a onclick="showdiv(event, 'delete','<?php echo str_replace('&','&amp;', $file['name']);?>');">删除</a></li>
                                            </ul></li>
                                            <?php }?>
                                        </td>
                                        <td class="updated_at"><?php echo ISO_format($file['lastModifiedDateTime']); ?></td>
                                        <td class="size"><?php echo size_format($file['size']); ?></td>
                                    </tr>
                                <?php }
                            }
                            foreach ($files['children'] as $file) {
                                // Files
                                if (isset($file['file'])) {
                                    if (substr($file['name'],0,1) !== '.' and $file['name'] !== $config['passfile'] and $file['name'] !== ".".$config['passfile'].'.swp' and $file['name'] !== ".".$config['passfile'].".swx") {
                                    if (strtolower($file['name']) === 'readme.md') $readme = $file;
                                    if (strtolower($file['name']) === 'index.html') {
                                        $html = curl_request(fetch_files(spurlencode(path_format($path . '/' .$file['name']),'/'))['@microsoft.graph.downloadUrl']);
                                        return output($html,200);
                                    } ?>
                                    <tr data-to>
                                        <!--<td class="updated_at"><?php $filenum++; echo $filenum;?></td>-->
                                        <td class="file">
                                            <ion-icon name="document"></ion-icon>
                                            <a href="<?php echo path_format($config['base_path'] . '/' . $path . '/' . encode_str_replace($file['name'])); ?>?preview" target=_blank>
                                                <?php echo str_replace('&','&amp;', $file['name']); ?>
                                            </a>
                                            <a href="<?php echo path_format($config['base_path'] . '/' . $path . '/' . str_replace('&','&amp;', $file['name']));?>">
                                                <ion-icon name="download"></ion-icon>
                                            </a>
                                            <?php if ($config['admin']) {?>&nbsp;&nbsp;&nbsp;
                                            <li class="operate">管理<ul>
                                                <li><a onclick="showdiv(event, 'rename','<?php echo str_replace('&','&amp;', $file['name']);?>');">重命名</a></li>
                                                <li><a onclick="showdiv(event, 'move','<?php echo str_replace('&','&amp;', $file['name']);?>');">移动</a></li>
                                                <li><a onclick="showdiv(event, 'delete','<?php echo str_replace('&','&amp;', $file['name']);?>');">删除</a></li>
                                            </ul></li>
                                            <?php }?>
                                        </td>
                                        <td class="updated_at"><?php echo ISO_format($file['lastModifiedDateTime']); ?></td>
                                        <td class="size"><?php echo size_format($file['size']); ?></td>
                                    </tr>
                                <?php }
                                }
                            }
                        } ?>
                    </table>
                    <?php
                    if ($files['folder']['childCount']>200) {
                        //echo json_encode($files['folder'], JSON_PRETTY_PRINT);
                        $pagenum = $files['folder']['page'];
                        $maxpage = ceil($files['folder']['childCount']/200);
                        $prepagenext = '<form action="" method="POST" id="nextpageform">
                        <input type="hidden" id="pagenum" name="pagenum" value="'. $pagenum .'">
                        <table width=100% border=0>
                            <tr>
                                <td width=60px align=center>';
                        //if (isset($_POST['nextlink'])) $prepagenext .= '<a href="javascript:history.back(-1)">上一页</a>';
                        if ($pagenum!=1) {
                            $prepagenum = $pagenum-1;
                            $prepagenext .= '
                            <a onclick="nextpage('.$prepagenum.');">上一页</a>
                            ';
                        }
                        $prepagenext .= '</td>
                                <td class="updated_at">
                                ';
                        //$pathpage = path_format($config['list_path'].$path).'_'.$page;
                        for ($page=1;$page<=$maxpage;$page++) {
                            /*if ($files['folder'][path_format($config['list_path'].$path).'_'.$page]) $prepagenext .= '  <input type="hidden" name="'.$path.'_'.$page.'" value="'.$files['folder'][path_format($config['list_path'].$path).'_'.$page].'">
                                    ';*/
                            if ($page == $pagenum) {
                                $prepagenext .= '<font color=red>' . $page . '</font> 
                                ';
                            } else {
                                $prepagenext .= '<a onclick="nextpage('.$page.');">' . $page . '</a> 
                                ';
                            }
                        }
                        $prepagenext = substr($prepagenext,0,-1);
                        $prepagenext .= '</td>
                                <td width=60px align=center>';
                        if ($pagenum!=$maxpage) {
                            $nextpagenum = $pagenum+1;
                            $prepagenext .= '
                            <a onclick="nextpage('.$nextpagenum.');">下一页</a>
                            ';
                        }
                            $prepagenext .= '</td>
                            </tr></table>
                            </form>';
                            echo $prepagenext;
                    }
                    if ($config['admin']) { ?>
                    <script src="//cdn.staticfile.org/jquery/1.10.2/jquery.min.js"></script>
    <div id="upload_div" style="margin:16px"><center>
        <input id="upload_file" type="file" name="upload_filename" >
        <button id="upload_submit" onclick="preup();">上传</button>
        <br><label id="upload_res"></label>
        </center>
    </div>
    <?php }
                    if ($readme) {
                        echo '</div></div></div><div class="list-wrapper"><div class="list-container"><div class="list-header-container"><div class="readme">
<svg class="octicon octicon-book" viewBox="0 0 16 16" version="1.1" width="16" height="16" aria-hidden="true"><path fill-rule="evenodd" d="M3 5h4v1H3V5zm0 3h4V7H3v1zm0 2h4V9H3v1zm11-5h-4v1h4V5zm0 2h-4v1h4V7zm0 2h-4v1h4V9zm2-6v9c0 .55-.45 1-1 1H9.5l-1 1-1-1H2c-.55 0-1-.45-1-1V3c0-.55.45-1 1-1h5.5l1 1 1-1H15c.55 0 1 .45 1 1zm-8 .5L7.5 3H2v9h6V3.5zm7-.5H9.5l-.5.5V12h6V3z"></path></svg>
<span style="line-height: 16px;vertical-align: top;">'.$readme['name'].'</span>
<div class="markdown-body" id="readme"><textarea id="readme-md" style="display:none;">' . curl_request(fetch_files(spurlencode(path_format($path . '/' .$readme['name']),'/'))['@microsoft.graph.downloadUrl'])
                            . '</textarea></div></div>';
                    }
                }
                } else {
                    echo '
<div>
	<center><h4>输入密码进行查看</h4>
	  <form action="" method="post">
		    <label>密码</label>
		    <input name="password1" type="password"/>
		    <button type="submit">查看</button>
	  </form>
    </center>
</div>';
                    $statusCode = 401;
                } }
                ?>
            </div>
        </div>
    </div>
    <div id="mask" style="position:absolute;display:none;left:0px;top:0px;width:100%;background-color:#000;filter:alpha(opacity=50);opacity:0.5"></div>
    <?php if ($config['admin']) { ?>
    <div id="rename_div" name="operatediv" style="position: absolute;border: 10px #CCCCCC;background-color: #FFFFCC; display:none">
        <div style="margin:16px">
        <label id="rename_label"></label><br><br><a onclick="operatediv_close('rename')" class="operatediv_close">关闭</a>
        <form action="" method="POST">
            <input id="rename_hidden" name="rename_oldname" type="hidden" value="">
            <input id="rename_input" name="rename_newname" type="text" value="">
            <button name="operate_action" type="submit">重命名</button>
        </form>
        </div>
    </div>
    <div id="delete_div" name="operatediv" style="position: absolute;border: 10px #CCCCCC;background-color: #FFFFCC; display:none">
        <div style="margin:16px">
        <br><a onclick="operatediv_close('delete')" class="operatediv_close">关闭</a>
        <form action="" method="POST">
            <label id="delete_label"></label>
            <label id="delete_input"></label>
            <input id="delete_hidden" name="delete_name" type="hidden" value="">
            <button name="operate_action" type=submit>确定删除</button>
        </form>
        </div>
    </div>
    <div id="encrypt_div" name="operatediv" style="position: absolute;border: 10px #CCCCCC;background-color: #FFFFCC; display:none">
        <div style="margin:16px">
        <label id="encrypt_label"></label><br><br><a onclick="operatediv_close('encrypt')" class="operatediv_close">关闭</a>
        <form action="" method="POST">
            <input id="encrypt_hidden" name="encrypt_folder" type="hidden" value="">
            <input id="encrypt_input" name="encrypt_newpass" type="text" value="">
            <?php if (getenv('passfile')!='') {?><button
