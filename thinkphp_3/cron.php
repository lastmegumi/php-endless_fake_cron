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

	// 测试函数
	// 每次 新闻 id ++
	private function test($arg = null){ 
		return strtotime("now");
	}

	// 禁用旧的工作
	private function DISABLE_OLD_JOBS($arg = null){
		$timerange = $arg['timerange']? intval($arg['timerange']):1; // 防止tr = 0
		$tr = 86400 * 30 * $timerange; // 定时 x 个月之前的禁用
		$mapj['edittime'] = array('lt', strtotime('now') - $tr);
		$mapj['status'] = 1;
		D('job')->where($mapj)->setField('status', 2);
	}
}
?>