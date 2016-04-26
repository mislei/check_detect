<?php
/**
 *	万方查重检测SDK
 *	@author  mislei <908060322@qq.com>
 *	@version 1.0
 *	
 *	usage：
 *	启用php.ini中php_soap.dll
 *	如果需要使用word转txt功能需要建议使用php5.4及以上版本，并启用php.ini中 com.allow_dcom = true   extension=php_com_dotnet.dll
 *	$options = array(
 *			'username' => "wbatchcheck",
 *			'password' => "f",
 *			'pathfile' => "111.txt",
 *			'savepath' => "/file/pdf/",
 *			'debug' => true,
 *	 		'logfile' => "./Log/log.txt"
 *			);
 *
 *	$detect = new CheckDetect($options);
 *
 *	$res = $detect->run();
 **/
class CheckDetect{
	
	const LOGIN_URL = "http://login.test.wanfangdata.com.cn/login.aspx";	//用户登陆 请求地址
	const WEBSERVICE_URL = "http://check.test.wanfangdata.com.cn/Ex2BatchDetectService.asmx?wsdl";	//WEBSERVICE 请求地址
	
	private $username;	//登陆账号
	private $password;	//登陆密码
	private $pathfile;	//检测txt文件的路径文件
	private $savepath;	//生成文件保存路径
	
	public $debug =  false;		//DEBUG状态
	public $logfile = 'detectLog.txt';	//日志文件
	/**
	 *	初始化CheckDetect类
	 *	@param array $options 初始化数据
	 **/
	public function __construct( $options ){
		
		$this->username = isset($options['username'])?$options['username']:'';
		$this->password = isset($options['password'])?$options['password']:'';
		$this->pathfile = isset($options['pathfile'])?$options['pathfile']:'';
		$this->savepath = isset($options['savepath'])?$options['savepath']:'';
		$this->debug = isset($options['debug'])?$options['debug']:false;
		$this->logfile = isset($options['logfile'])?$options['logfile']:'detectLog.txt';
		$this->log(date('Y-m-d H:i:s',time()));
		$this->log('执行初始化方法');
		
	}
	/**
	 *	检测执行方法
	 *	@return Boolean
	 **/
	public function run($pathfile = null){
		if(!empty($pathfile))
			$this->pathfile = $pathfile;
		$beginTime = time();
		$cookie = $this->checkuser();
		$this->log('用户登陆模块共耗时：'.(time()-$beginTime).'s');
		$beginTime = time();
		$res = $this->checktext($cookie);
		$this->log('查重检测模块共耗时：'.(time()-$beginTime).'s');
		return $res;
	}
	
	/**
	 *	用户登陆
	 *	@return array cookie数据
	 **/
	private function checkuser(){
		$url = self::LOGIN_URL."?userid=".$this->username."&password=".$this->password;
		$header = get_headers($url, 1);
		$this->log($header);
		foreach($header as $k=>$v){
			if($k == 'Set-Cookie'){
				if(is_array($v)){
					foreach($v as $j=>$c){
						$cookie[$j] = explode("=",$c);
						$cookie[$j]['value'] = explode(";",$cookie[$j][1]);
					}
				}
			}
		}
		
		return $cookie;
	}
	/**
	 *	查重检测
	 *	@param array $cookie 登陆cookie数据
	 *	@return array 
	 **/
	private function checktext($cookie){
		try {
			$content = file_get_contents($this->pathfile);	//读取文本信息到变量
			$encode = mb_detect_encoding($content,array("ASCII","GB2312","GBK","UTF-8"));	//检测文本编码格式
			$content = mb_convert_encoding($content,"UTF-8",$encode);	//转换编码格式UTF-8
		//	echo $content;
			$client = new SoapClient(self::WEBSERVICE_URL);			
			//var_dump($client->__getFunctions());			
			//var_dump($client->__getTypes());
		
			$arrPara = array(new BatchCopyDetect($content));	//传入参数
			$client->__SetCookie($cookie[0][0],$cookie[0]['value'][0]);	//设置cookie
			$client->__SetCookie($cookie[1][0],$cookie[1]['value'][0]);	//设置cookie
			
			$arrResult = $client->__Call("BatchCopyDetect",$arrPara);
		
			$obj = $arrResult->BatchCopyDetectResult;
			$arr = is_object($obj) ? get_object_vars($obj) : $obj;
			
			switch($arr['DetectStatus']){
				case 'Success' :
					$this->log("查询成功");
					$file = basename($this->pathfile).'.pdf';
					$this->createDir($this->savepath);
					file_put_contents($this->savepath.$file, $arr['DetailReport']);
					unset($arr['SimpleReport']);
					unset($arr['DetailReport']);
					unset($arr['PDFFulltextReport']);
					$arr['filePDF'] = $this->savepath.$file;
					break;
				case 'WordsCountOutOfRange' :					
					$arr['errMsg'] = '检测字数超出范围（低于200或超过1000000字）';
					$this->log($arr['errMsg']);
					break;
				case 'ServiceNotOpen' :
					$arr['errMsg'] = '登录的账号未开通相似性检测服务';
					$this->log($arr['errMsg']);
					break;
				case 'OutOfMoney' :
					$arr['errMsg'] = '余额不足';
					$this->log($arr['errMsg']);
					break;
				case 'AuthenticateFailed' :
					$arr['errMsg'] = '登录失败';
					$this->log($arr['errMsg']);
					break;
				case 'DetectFailed' :
					$arr['errMsg'] = '检测失败（建议稍后重新检测）';
					$this->log($arr['errMsg']);
					break;
				case 'TransFailed' :
					$arr['errMsg'] = '记账失败（建议稍后重新检测）';
					$this->log($arr['errMsg']);
					break;
			}
			$this->log($arr);
			return $arr;	//返回数组
			
		} catch (SOAPFault $e) {
			return $e;
		}
	}
	
	 /**
     * 日志记录，可被重载。
     * @param mixed $log 输入日志
     * @return mixed
     */
    private function log($log){
		if ($this->debug) {
			if (is_array($log)) 
				$log = print_r($log,true);
			$num = strripos($this->logfile,'/');
			$path = substr($this->logfile,0,($num+1));
			$this->createDir($path);
			file_put_contents($this->logfile, $log."\r\n", FILE_APPEND);
		}
    }
	/* 
	* 功能：循环检测并创建文件夹 
	* 参数：$path 文件夹路径 
	* 返回： 
	*/ 
	function createDir($path){
		if (!file_exists($path)){ 
			$this->createDir(dirname($path)); 
			mkdir($path, 0777); 
		} 
	}
	
	/**
	 *	读取word内容保存为txt
	 */
	function wordToText($pathfile,$nowpath){
		$word = new COM("word.application") or die ("Could not initialise MS Word object.");
		$word->Documents->Open(realpath($pathfile));
	 
		// Extract content.
		//$content = (string) $word->ActiveDocument->Content;
		$content= $word->ActiveDocument->content->Text; 
		
		 
		$word->ActiveDocument->Close(false);
	 
		$word->Quit();
		$word = null;
		unset($word); 
		
		$this->createDir($nowpath);		
		$file = basename($pathfile);
		file_put_contents($nowpath.$file.'.txt', $content);
		return $nowpath.$file.'.txt';
	}
	
}

/**
 *	webservice 参数
 */
class BatchCopyDetect {
	var $detectRequest;
	function __construct($content,$pathfile = '111.txt'){
		$this->detectRequest = new BatchDetectRequest($content,$pathfile);
	}
}
/**
 *	webservice 参数
 */
class BatchDetectRequest {
	var  $TaskName = '批量检测';
	var  $FileName = '111.txt';
	var  $byteContent;
	function __construct($content,$pathfile){
		$content = iconv('utf-8','utf-16le',$content);	//转换文本编码
		$this->byteContent = $content;
		$this->FileName = basename($pathfile);	//读取文件名
	}
};
