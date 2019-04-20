<?php
/*
 * 脚本定时任务
 * @Created  15-1-6
 */
namespace Admin\Controller;

use Think\Controller;

class CronController extends CommonController {
	public $setting;

	function __Construct(){
		parent::__construct();
		$this->getSetting();
	}

	private function getSetting(){
		$push_setting = D('cron_status')->select();
		$this->setting = array();
		foreach ($push_setting as $p) {
			$this->setting[$p['option_name']] = $p['option_value'];
		}
	}

	function message(){
		if($this->setting['status'] == 0){return "脚本停止中";}
		if($this->setting['status'] && strtotime('now') - $this->setting['last_run'] <= $this->setting['cron_interval']){
			$count = D('cron')->where(array('status' => 1))->field("count(*) as count")->find();
			return '运行良好:' . $this->setting['note'] . ', 共计' . $count['count'] .'个函数正在运行.';}
		if($this->setting['status'] && strtotime('now') - $this->setting['last_run'] > ($this->setting['cron_interval'] * 1.25))	{
			$idle = strtotime('now') - $this->setting['last_run'];
			return '运行异常 请检查：距离最后一次运行'. date('Y-m-d H:i:s', $this->setting['last_run']).'已经过' . 
					$idle .'秒（执行间隔为'. $this->setting['cron_interval'].'秒）';}		
	}

	function PAUSE($id = null){
		if(!$id){return;}
		D('cron')->where(array('id' => $id))->setField('status', 0);
		$this->redirect("index");
	}

	function RESUME($id = null){
		if(!$id){return;}
		D('cron')->where(array('id' => $id))->setField(array('status' => 1, 'start_time' => strtotime('now'), 'runtimes' => 0));
		$this->redirect("index");
	}

	function RECOUNT($id = null){
		if(!$id){return;}
		D('cron')->where(array('id' => $id))->setField('runtimes' , 0);
		$this->redirect("index");
	}

	function index(){
		$data = D('cron')->
		select();
		$this->assign('data', $data);
		$this->assign('message', $this->message());
		$this->display();		
	}

	function setting(){
		$this->assign("setting", $this->setting);
		$this->assign('message', $this->message());
		$this->display();
	}

	function UPDATESETTING(){
		foreach(I('cron') as $key => $val)
		D('cron_status')->where(array('option_name' => $key))->setField('option_value' , $val);
		$this->redirect("setting");
	}

	function START(){
		ignore_user_abort();
		set_time_limit(0);
		ob_end_clean();
		header("Connection: Close");      //告诉浏览器关闭当前连接,即为短连接
		$msg['code'] = 200;
		$msg['message'] = '开始执行...';
		ob_start();
		echo json_encode($msg);		
		$size=ob_get_length(); 
        header("Content-Length: $size");  //告诉浏览器数据长度,浏览器接收到此长度数据后就不再接收数据
        ob_end_flush();  
        flush();
		D('cron_status')->where(array('option_name' => 'status'))->setField('option_value', 1);
		D('cron_status')->where(array('option_name' => 'start_time'))->setField('option_value', strtotime('now'));
		D('cron')->where(array('status' => 1))->setField('start_time', strtotime('now'));
		$this->_RUN();
	}

	function STOP(){
		D('cron_status')->where(array('option_name' => 'status'))->setField('option_value', 0);
		D('cron_status')->where(array('option_name' => 'is_run_now'))->setField('option_value', 0);
	}

	private function _LOG(){
		D('cron_status')->where(array('option_name' => 'last_run'))->setField('option_value', strtotime('now'));
	}

	private function _IS_RUN_NOW($b = null){
		if($b === null){
			$is_run_now = D('cron_status')->where(array('option_name' => "is_run_now"))->find();
			return $is_run_now['option_value'] == '1'? true:false;
		}
		D('cron_status')->where(array('option_name' => 'is_run_now'))->setField('option_value', $b);
	}

	function STATUS(){
		$status = D('cron_status')->where(array('option_name' => 'status'))->find();
		return $status['option_value'] == 1? true: false;
	}

	private function _RUN(){
		while($this->STATUS()):
			if($this->_IS_RUN_NOW()){sleep(5); continue;}
			$this->_IS_RUN_NOW(1);
			$ops = D('cron')->where(array('status' => 1))->select();
			$t1 = strtotime('now');
			foreach ($ops as $op) {
				if(strtotime('now') - $op['last_run'] < $op['wf_interval']){continue;}
				try {
					$func = $op['opration'];
					if(!empty($op['arg'])):
						foreach(array_filter(explode(',', $op['arg'])) as $a):
							$args[explode(':', $a)[0]] = explode(':', $a)[1];
						endforeach;
						$res = $this->$func($args);
					else:
						$res = $this->$func();
					endif;
					D('cron')->where(array('id' => $op['id']))->setField('runtimes', $op['runtimes'] + 1);
				} catch (Exception $e) {
					D('cron')->where(array('id' => $op['id']))->setField('note', $e->getMessage());
				}finally{
					D('cron')->where(array('id' => $op['id']))->setField('last_run', strtotime('now'));
				}
			}
			$this->_LOG();
			$this->_IS_RUN_NOW(0);
			$cost = strtotime('now') - $t1;
			D('cron_status')->where(array('option_name' => 'note'))->setField('option_value', "上次执行脚本总共耗时" . $cost . "秒");
			fastcgi_finish_request();
			$this->getSetting();
			sleep($this->setting['cron_interval']);
		endwhile;
	}

	private function Sitemap(){
		$con = A('sm');
		$con->new_sitemap();
	}

	private function Rss(){
		$con = A('sm');
		$con->new_feed();
	}


	// 测试函数
	// 每次 新闻 id ++
	private function test($arg = null){ 
		return D('zan')->execute("UPDATE wf_zan SET newid = newid + 1 WHERE uid = 941");
	}

	// 禁用旧的工作
	private function DISABLE_OLD_JOBS($arg = null){
		$timerange = $arg['timerange']? intval($arg['timerange']):1; // 防止tr = 0
		$tr = 86400 * 30 * $timerange; // 定时 x 个月之前的禁用
		$mapj['edittime'] = array('lt', strtotime('now') - $tr);
		$mapj['status'] = 1;
		D('job')->where($mapj)->setField('status', 2);
	}

	//发送订阅信息 参数
	// timeid  
	// 1 : 每天
	// 2 : 每周
	// 3 : 每月
	private function Subscribe($arg=  null){
		$timeid = $arg['timeid']? intval($arg['timeid']):0;
		if(!$timeid){return;}
		$this->SendSubscribe($timeid);
	}

	protected function SendSubscribe($timeid){

		$name="您在美东人才网上订阅的职位有新的消息啦！";
		$sub  = M('Subscribe')->where('status=1 and timeid='.$timeid)->select();
		foreach ($sub as $val ) {
			$mail= $val['email'];
			$city= $val['hopecid'];
			$pay= $val['hopepay'];
			$keywords = explode(',', $val['hopejob']);
			$tmpArr = array();
			foreach($keywords  as $key =>$val) {
			array_push($tmpArr, array('like','%'.$val.'%'));
			 }
			 array_push($tmpArr,'or');
			$map['status']= "1";
			$map['cityid'] = $city;
			$map['payid']= $pay;
			$map['title'] = $tmpArr;  
			switch ($timeid) {
				case 1:
					$time = strtotime('-1 day');break;
				case 2:
					$time = strtotime('-7 day');break;
				case 3:				
					$time = strtotime('-1 month');	break;
				default:
					$time = false;
					break;
			}
			if(!$time){return;}
			$map['addtime'] = array('egt',$time);
			$order = 'addtime desc';

			$do    = M('job'); 
			$joblist = $do->order($order)->where($map)->select();

			foreach ($joblist as $k => $v) {
			$joblist[$k]['title']  = M('Job')->where('id = ' . $v['id'])->getField('title');
			$offer   = '<div class="nui-fClear sR0"><br /> 
						<table style="width: 99.8%;height:99.8% ">
						<tbody>
						<tr>
						<td style="background:#FAFAFA"> 
						<div style="padding:0 12px 0 12px;margin-top:18px">
						<p style="background-color: #C7EDCC;border: 0px solid #DDD;padding: 10px 15px;margin:18px 0"> <a href="https://www.eusjob.com/index.php/Home/Job/item?jobid='.$v['id'].'"  target="_blank">'.$joblist[$k]['title'].'</a></p>
						</div>
						</td>
						</tr>
						</tbody>
						</table>
						</div>';
			$content = $offer;
			//dump($content);
			sendMail($mail,$name, $content);
			}
		}
	}

}
?>