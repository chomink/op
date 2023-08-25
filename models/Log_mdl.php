<?php

class Log_mdl extends CI_Model {

	function __construct()
	{
		parent::__construct();
	}

	/**
    * 주문별 GPS 도착알림
	*/
	function regist_gps_alarm()
	{	
		$insert['no_order']		        = $this->no_order;
		$insert['no_shop']		        = $this->no_shop;
		$insert['no_user']		        = $this->no_user;
		$insert['no_device']	        = $this->no_device ? $this->no_device : 0;
		$insert['at_lat']		        = $this->at_lat;
		$insert['at_lng']		        = $this->at_lng;
		//$insert['at_lat']				= NULL;
		//$insert['at_lng']              = NULL;
		$insert['at_distance']          = $this->at_distance;
		$insert['yn_gps_status']        = $this->yn_gps_status;
		$insert['cd_alarm_event_type']  = $this->cd_alarm_event_type;
        $insert['ct_update']            = $this->ct_update;

		$this->log_db->set('dt_event_create', 'now()',FALSE);
		$this->log_db->insert('order_alarm_event_log', $insert);
	}


	/**
	* FnB 주문 픽업시간 업데이트 로그
	 */
	function update_order_pickuptime_log()
	{	
		$insert['no_order']		        = $this->no_order;
		$insert['no_shop']		        = $this->no_shop;
		$insert['no_user']		        = $this->no_user;
		$insert['no_device']	        = $this->no_device ? $this->no_device : 0;
		$insert['at_lat']		        = ($this->at_lat)?$this->at_lat:NULL;
		$insert['at_lng']               = ($this->at_lng)?$this->at_lng:NULL;
		$insert['yn_gps_status']        = $this->yn_gps_status ? $this->yn_gps_status : NULL;
		$insert['cd_alarm_event_type']  = $this->cd_alarm_event_type;
		$insert['dt_pickup_time_chg']   = $this->dt_pickup_time_chg;
		$insert['ct_update']			= $this->ct_update;
	
		$this->log_db->set('dt_event_create', 'now()',FALSE);
		$this->log_db->insert('order_alarm_event_log', $insert);
	}


	// 이벤트 로그 중 취소사유 검색
	function get_alarm_event_cancel()
	{
		$sql = "SELECT cd_alarm_event_type
				FROM order_alarm_event_log 
				WHERE cd_alarm_event_type in ('607999','607380','607370','607900') 
					AND no_user = ?
					AND no_order = ?
				ORDER BY dt_event_create DESC LIMIT 1";

		$result = $this->log_db->query($sql, Array($this->no_user, $this->no_order))->row_array();

		return $result;
	}


}
