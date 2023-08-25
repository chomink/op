<?php
defined('BASEPATH') OR exit('No direct script access allowed');
setlocale(LC_CTYPE, 'ko_KR.euc-kr');

class Order extends CI_Controller {

    function __construct()
    {
        parent::__construct();
    }


    /**
     * 통합 주문 리스트
     */
    public function lists()
    {
        ##1 요청파라미터 셋팅
        $request = process_init();

        ##2 필수 파라미터 체크
        $this->is_pickup_service = FALSE;
        if($request['cd_service'] == '900100')
        {
            $this->is_pickup_service = TRUE;
            $required = array('ds_login','no_user','cd_service','ds_search_yymm');
        }
        else
        {
            $required = array('ds_login','no_user','cd_service','ds_search_yymm');
        }
        empty_param_chk($request,$required);

        ##3 파라미터 셋팅
        $this->ds_login				= $request['ds_login'];									// 로그인정보
        $this->no_user				= $request['no_user'];									// 회원번호
        $this->cd_service			= $request['cd_service'];								// 서비스구분
        $this->ds_search_yymm		= $request['ds_search_yymm'];							// 조회년월
        $this->ct_date				= $request['ct_date'];									// 리스트 마지막 Row 날짜 (ex)20170520105059
        $this->list_biz_kind		= $request['list_biz_kind'];							// 리스트노출 업종 - 없을경우 전체리스트
        $this->list_item			= $request['list_item'];	                            // 리스트구분 > 업종코드로 대체 ( APP업데이트이후 제거 2020-09-08)
        $this->list_cnt	= 20; // 리스트갯수


        ##4 권한인증 체크 0200
        authorize($request);

        ##5-1 응답문셋팅::파라미터체크 0300
        $response = array();
        if(false) // 결제방식이 올바른지 체크
        {
        }
        else
        {
            // 디비연결
            $this->slave_db = $this->load->database('slave', TRUE);
            $this->load->model('order_mdl');

            // 기존 버전인 경우 list_item 구분으로 조회 [2018.06.07 정비 이전]
            if($this->list_item)
            {
                // 주문리스트
                if($this->list_item == "park")
                {
                    $list_order = $this->order_mdl->get_booking_list();
                }else{
                    $list_order = $this->order_mdl->get_order_list_merge(); //get_order_list_merge
                }
            }
            // 코드기준 리스트조회 [2020.09.01 업데이트]
            // 신규 버전인 경우 list_item 값은 없고 cd_biz_kind 로 구분하여 조회 [2018.06.07 정비 추가]
            else
            {
                // FnB의경우 결제오류건 미노출 처리로 리스트 구분
                $this->pickup_list = false; // FnB 업종여부

                if(is_array($this->list_biz_kind))
                {
                    if(in_array('201100',$this->list_biz_kind))
                    {
                        $this->pickup_list = true;
                    }
                    $this->list_biz_kind_merge= "'".implode("','",$this->list_biz_kind)."'";
                }
                $list_order = $this->order_mdl->get_order_list_kind_merge();
            }

            $add_log['pickup_list']			=	$this->pickup_list;
            $add_log['list_biz_kind_cnt']	=	count($this->list_biz_kind);
            $add_log['list_biz_kind']		=	$this->list_biz_kind;
            $add_log['list_biz_kind_merge']	=	$this->list_biz_kind_merge;

            $response['ct_list']	=  $this->list_cnt;	//리스트 갯수
            $response['list_item']	 = $this->list_item;
            $response['list_order']	=	$list_order;

            // 정상처리
            $response  = set_success_param($response);
        }
        ##6 응답
        process_close($request,$response,TRUE,$add_log);
    }



    /**
     * 주문 상세
     */
    public function detail()
    {
        ##1 요청파라미터 셋팅
        $request = process_init();

        ##2 필수 파라미터 체크
        $required = array('ds_login','no_user','no_order');
        empty_param_chk($request,$required);

        ##3 파라미터 셋팅
        $this->ds_login			= $request['ds_login'];			// 로그인정보
        $this->no_user			= $request['no_user'];			// 회원번호
        $this->no_order			= $request['no_order'];			// 주문번호

        ##4 권한인증 체크 0200
        authorize($request);

        ##5-1 응답문셋팅::파라미터체크 0300
        $response = array();
        if(false) // 결제방식이 올바른지 체크
        {
        }
        else
        {
            // 디비연결
            $this->slave_db = $this->load->database('slave', TRUE);
            $this->load->model('order_mdl');
            $this->load->model('oil_mdl');
            $this->load->model('card_mdl');
            $this->load->model('payment_mdl');
            $this->load->model('code_mdl');
            $this->load->model('beacon_mdl');
            $this->load->model('event_mdl');

            ## ----------------------------------------------------------------------------------
            ## 회원가입시 핸드폰 국가확인 - 태국시연용
            ## ----------------------------------------------------------------------------------
            $this->load->model('member_mdl');
            $member_info	= $this->member_mdl->get_user_info_all();
            $user_lang		= ($member_info['ds_nation']=="en")?"en":"kr";

            $code = $this->code_mdl->get_code_all();

            // 발렛주문상세내역
            $order_valet_info	= $this->order_mdl->valet_detail();
            $response['order_valet_info']	=	$order_valet_info;

            ##  주문내역 정보
            $order_info		= $this->order_mdl->get_order_info();
            $response['no_order']				=	$order_info['no_order'];
            $response['cd_biz_kind']			=	$order_info['cd_biz_kind'];
            $this->cd_biz_kind  				=	$order_info['cd_biz_kind'];

            $response['no_order_user']			=	get_no_order_user($order_info['no_order']);
            $response['nm_order']				=	$order_info['nm_order'];
            $response['cd_gas_kind']			=	$order_info['cd_gas_kind'];
            $response['at_price']				=	$order_info['at_price'];
            $response['at_disct']				=	$order_info['at_disct'];			// 쿠폰할인금액
            $response['at_cpn_disct']			=	$order_info['at_cpn_disct'];		// 쿠폰할인금액
            $response['at_point_disct']			=	$order_info['at_point_disct'];		// 브랜드포인트할인금액
            $response['at_cash_disct']			=	$order_info['at_cash_disct'];		// 캐시할인금액
            $response['at_event_cash_disct']	=	$order_info['at_event_cash_disct']; // 이벤트캐시 할인금액
            $response['at_bank_disct']			=	$order_info['at_bank_disct'];		// 결재카드할인금액 - IBK송금할인

            $response['cd_order_status']		=	$order_info['cd_order_status'];
            $response['cd_payment_status']		=	$order_info['cd_payment_status'];
            $response['cd_pickup_status']		=	($user_lang =='en')?"602200":($order_info['cd_pickup_status'] == "602390" ? "602100" : $order_info['cd_pickup_status']);

            $response['cd_alarm_event_type']	=	$order_info['cd_alarm_event_type'];
            $response['cd_call_shop']			=	$order_info['cd_call_shop'];
            $response['at_add_delay_min']		=	$order_info['at_add_delay_min'];
            $response['at_event_support']		=	$order_info['at_event_support'];
            $response['dt_pickup']				=	set_date_format($order_info['dt_pickup'],5);
            $response['dt_pickup_status']		=	set_date_format($order_info['dt_pickup_status'],5);
            $response['dt_order']				=	set_date_format($order_info['dt_reg'],5);

            $response['at_liter_gas']			=	$order_info['at_liter_gas'];		//예약 결제리터
            $response['at_liter_real']			=	$order_info['at_liter_real'];		//실제 결제리터
            $response['yn_gas_order_liter']		=	$order_info['yn_gas_order_liter'];	//	결제주유 타입(리터 1 , 금액 2)
            $response['at_price_real_gas']		=	$order_info['at_price_real_gas'];	//실제주유총금액

            $response['ds_unit_id']				=	$order_info['ds_unit_id'];			//노즐번호
            $response['no_approval']			=	$order_info['no_approval'];			//승인번호
            $response['dt_approval']			=	$order_info['dt_approval'];			//승인일시

            $response['dt_payment_status']		=	set_date_format($order_info['dt_payment_status'],5);	//결제상태변경일시 (취소시 - 취소일시)
            $response['at_p_point_for_add']		=	$order_info['at_p_point_for_add'];	//GS적립예정포인트


            $response['at_price_pg']			=	$order_info['at_price_pg'];			// 실 결제 금액
            $response['at_commission_rate']		=	$order_info['at_commission_rate'];	// 건당수수료
            $response['ds_request_msg']			=	$order_info['ds_request_msg'];		// 주문요청사항

            $response['ds_franchise_num']		=	$order_info['ds_franchise_num'];		// 가맹점번호 - 주문내역
            $response['dt_pickup_day']			=	str_replace("-","", substr($order_info['dt_pickup'],0,10)); // 정비예약일 ex.20180508 (픽업일)

            ##  주문상태 정보
            $this->cd_order_status		= $order_info['cd_order_status'];
            $this->cd_pickup_status		= $order_info['cd_pickup_status'];
            $this->cd_payment_status	= $order_info['cd_payment_status'];

            ##  카드사조회 관련 정보
            $this->no_user				=	$order_info['no_user'];
            $this->no_card				=	$order_info['no_card'];

            ## 취소가능여부 / PIN 이미지

            $response['yn_payment_cancel']		=	'N'; // 취소 불가능

            // 주유업종인 경우
            if($this->cd_biz_kind  == '201300')
            {
                // 취소가능한 경우 - 구매당일 23시30분 까지
                $date_parse = date_parse($order_info['dt_reg']);
                $limit_date = $date_parse[year].'-'.$date_parse[month].'-'.$date_parse[day].'  23:30:00';

                if(date('Y-m-d H:i:s') < $limit_date)
                {
                    $response['yn_payment_cancel']		=	'Y';
                }
                // 주문매장정보
                $this->no_shop = $order_info['no_shop'];
                $order_shop		= $this->oil_mdl->get_order_shop();

                //브랜드 PIN이미지 정보(주유일경우 노출)
                $response['ds_pin']			=	imgpath($order_shop['ds_pin']);
            }
            else
            {
                if($order_info['cd_pickup_status'] == '602100') // 주문요청은 접수전이라 취소가능
                {
                    $response['yn_payment_cancel']		=	'Y';
                }
                else if($order_info['cd_pickup_status'] == '602200' and $order_info['at_add_delay_min'] > 0) // 접수할때 지연시킨경우
                {
                    $dp = date_parse($order_info['dt_pickup_status']);
                    $pickup_status_stemp = mktime($dp['hour'],$dp['minute'],$dp['second'],$dp['month'],$dp['day'],$dp['year']) +300;
                    if($pickup_status_stemp  > time()) // 변경시간 5분이 지나지 않은경우 취소가능
                    {
                        $response['yn_payment_cancel']		=	'Y';
                    }
                }

                // 주문한 매장정보 조회
                $this->no_shop = $order_info['no_shop'];
                $order_shop		= $this->order_mdl->get_order_shop();

                //브랜드 PIN이미지 정보
                $response['ds_pin']			=	"";
            }

            ## 주문매장 정보
            $response['no_partner']				=	$order_shop['no_partner'];
            $response['no_shop']				=	$order_shop['no_shop'];
            $response['nm_partner']				=	$order_shop['nm_partner'];
            $response['nm_shop']				=	$order_shop['nm_shop'];
            $response['at_lat']					=	$order_shop['at_lat'];
            $response['at_lng']					=	$order_shop['at_lng'];
            $response['ds_address']				=	$order_shop['ds_address'].$order_shop['ds_address2'];
            $response['ds_tel']					=	$order_shop['ds_tel'];
            $response['ds_image_pick1']			=	imgpath($order_shop['ds_image_pick1']);
            $response['ds_image_pick2']			=	imgpath($order_shop['ds_image_pick2']);
            $response['ds_image_pick3']			=	imgpath($order_shop['ds_image_pick3']);
            $response['ds_image_pick4']			=	imgpath($order_shop['ds_image_pick4']);
            $response['ds_image_pick5']			=	imgpath($order_shop['ds_image_pick5']);
            $response['ds_common_notice']		=   $this->config->item('ds_common_notice');// 공통유의사항
            $response['ds_partner_notice']		=  '';		//$partner['ds_shop_notice'];	// 브랜드별 유의사항
            $response['ds_shop_notice']			=  '';		//$shop['ds_shop_notice'];		// 매장별 유의사항

            $response['ds_biz_num']				=	$order_shop['ds_biz_num'];				// 주유소 사업자번호
            $response['ds_new_adr']				=	$order_shop['ds_new_adr'];				// 주유소 신주소
            $response['nm_os']					=	$order_shop['nm_os'];					// 주유소상호
            $response['nm_owner']				=	$order_shop['nm_owner'];				// 대표자명

            ##  주문결제 정보
            $payment_info  = $this->payment_mdl->get_order_payment_last_all();
            $response['cd_reject_reason']	=	$payment_info['cd_reject_reason'];
            $response['nm_reject_reason']   =   $code[$payment_info['cd_reject_reason']];

            $response['cd_pg_result']		=	($payment_info['cd_pg_result'])?$payment_info['cd_pg_result']:0; //가승인실패 -604940
            $response['nm_pg_result']       =   $code[$payment_info['cd_pg_result']];

            ##  결제카드정보 ( 카드사 ) [OTC개발 ]
            if(	$order_info['cd_payment_method'] == "504100"){ // 빌키결제
                $card_info		= $this->card_mdl->get_order_card_info();
            }else{
                $card_info		= $this->card_mdl->get_order_card_info_nice();
            }

            if($card_info)
            {
                $card_corp					= $code[$card_info['cd_card_corp']];
                $response['nm_card_corp']	= $card_corp."카드";			// 카드사 정보
                $response['no_card_user']	= $card_info['no_card_user']; // 결제카드번호 뒷4자리
            }

            // 상태 [2018.07.19 김목영 추가]
            $status_arr  = get_order_status($order_info['cd_biz_kind'], $order_info['cd_order_status'], $order_info['cd_pickup_status'], $order_info['cd_payment_status'], $order_info['cd_alarm_event_type'], $payment_info['cd_pg_result']);
            $response['cd_status'] = $status_arr['cd_status'];
            $response['nm_status'] = $status_arr['nm_status'];

            ## 오윈마스터계정 상태정보
            $yn_master = false;

            //테스트어드민일경우 0  /  일반유저 1 (김범준/조기상/조민경)
            $admin_member	= array('2516010011593318' ,'2507768405767753','2494489608282748');
            $owin_tester = (in_array($this->no_user, $admin_member , true))?"0":"1";
            if($owin_tester < 1)
            {
                $yn_master = true;
            }
            $response['yn_master'] = $yn_master;

            ## 주문예약방식정보 [QR 개발] // CarId 505100 / QR 505200
            $response['cd_booking_type']	=	$order_info['cd_booking_type'];


            ##  주문상품리스트
            if($this->cd_biz_kind  == '201300') // 주유업종인 경우
            {
                $list_order_product	= $this->order_mdl->get_oil_order_product();
            }
            else if($this->cd_biz_kind  == '201610') // 정비업종인 경우
            {
                $list_order_product	= $this->order_mdl->get_order_shop_product();
            }
            else  // 주유, 정비외 업종
            {
                $list_order_product	= $this->order_mdl->get_order_product();
            }
            $response['ct_order']	=	count($list_order_product);
            $response['list_order_product']	=	$list_order_product;

            ## 기기번호 - 주문건기중
            $response['no_device']				=	$order_info['no_device'];

            ## 기기번호 - 실시간 회원에 정상등록된 기기
            //$beacon_user_info = $this->beacon_mdl->get_my_beacon();
            //$no_device_user = $beacon_user_info[0]['no_device'];
            //$response['no_device_user']	=	$no_device_user;

            ## 최근 주유상태여부 - 프리셋
            $newest_process_info		= $this->order_mdl->get_newest_order_process_info();
            $this->cd_order_process		= $newest_process_info['cd_order_process'];
            $this->cd_order_status		= $order_info['cd_order_status'];
            $this->cd_pickup_status		= $order_info['cd_pickup_status'];
            $this->cd_payment_status	= $order_info['cd_payment_status'];

            $response['cd_order_process']		= $this->cd_order_process;

            ## ------------------------------------------------------------------------------------
            ## 기기보유여부 ( 주유 /FnB )
            ## ------------------------------------------------------------------------------------
            $yn_device = ($order_info['no_device'])?"Y":"N";
            $response['yn_device']	=	$yn_device;

            ## ------------------------------------------------------------------------------------
            ## 주유 Carin 여부
            ## ------------------------------------------------------------------------------------
            // 현재주문시간
            $this->order_dt_reg = $order_info['dt_reg'];

            //이전주문정보
            $prev_order_info				= $this->order_mdl->get_prev_order_info();
            $this->prev_order_dt_reg		= $prev_order_info['dt_pickup_status'];
            $today_first					= date("Y-m-d")." 00:00:00";
            $this->prev_order_dt_reg		= (!$this->prev_order_dt_reg)?$today_first:$this->prev_order_dt_reg;


            // 주문시간 30분전
            //$this->prev_order_dt_reg		= date("Y-m-d H:i:s", strtotime("$this->order_dt_reg -30 minutes"));

            // 현재시간 30분전
            $this->prev_order_dt_reg		= date("Y-m-d H:i:s", strtotime("-60 minutes"));

            // 주문건 관련 CarIn 정보 [시작버튼 표시]
            $join_carid_info = $this->order_mdl->get_join_carid_info();
            $join_carin_cnt			= count($join_carid_info);
            $yn_carin = "N";
            if($join_carin_cnt > 0)
            {
                $yn_carin = "Y";
            }

            $add_log['CHK_order_dt_reg']		=	$this->order_dt_reg;
            $add_log['CHK_now_dt_reg']				=	date("Y-m-d H:i:s");
            $add_log['CHK_prev_order_dt_reg']	=	$this->prev_order_dt_reg;
            $add_log['CHK_join_carin_cnt']		=	$join_carin_cnt;

            $response['yn_carin']			=	$yn_carin;

            ## ------------------------------------------------------------------------------------
            ## 주유 프리셋여부
            ## ------------------------------------------------------------------------------------
            //$yn_preset				= ($this->cd_order_process > 616100 && $this->cd_order_process < 616500)?"Y":"N";;// 프리셋세팅이후 Y
            $yn_preset				= "N";
            switch($this->cd_order_process)
            {
                case "616100" :$yn_preset = "N"; break;
                case "616200" :$yn_preset = "Y"; break; // 주유시작
                case "616300" :$yn_preset = "Y"; break; // 주유중
                case "616400" :$yn_preset = "Y"; break; // 결제진행
                case "616500" :$yn_preset = "Y"; break; // 결제완료
                case "616910" :$yn_preset = "N"; break; // 유종오류
                case "616920" :$yn_preset = "N"; break; // 주유기 상태확인

                case "616930" :$yn_preset = "N"; break; //정차불가
                case "616950" :$yn_preset = "N"; break;
                case "616990" :$yn_preset = "N"; break;
                case "616991" :$yn_preset = "N"; break;
                case "616999" :$yn_preset = "N"; break;
                default : $yn_preset = "N"; break;
            }
            $response['yn_preset']	= $yn_preset;

            $add_log['CHK_cd_order_status']		= $this->cd_order_status;
            $add_log['CHK_cd_pickup_status']	= $this->cd_pickup_status;
            $add_log['CHK_cd_payment_status']	= $this->cd_payment_status;
            $add_log['CHK_cd_order_process']	= $this->cd_order_process;

            ## 주유 DP2.0 설치여부 - 매장기준  [시작버튼 표시]
            $yn_dp2_install = "N";
            $shop_oil_info = $this->oil_mdl->get_oil_shop_bill_info();
            $yn_dp2_install = $shop_oil_info['yn_dp2'];
            $response['yn_dp2_install']	=	$yn_dp2_install;

            ## 주유소 셀프여부 [시작버튼 표시]
            //$yn_self = $shop_oil_info['yn_self'];
            $yn_self	= $order_shop['yn_self'];
            $response['yn_self']		= $yn_self;

            ## ------------------------------------------------------------------------------------
            ## 주유가이드 이미지 / 예약 안내 -- 이미지 작업필요
            ## ------------------------------------------------------------------------------------
            $guide_image			= "";
            $guide_image_preset		= "";

            $action_guide_main_msg	= "";
            $action_guide_sub_msg	= "";

            if($this->cd_biz_kind == "201300")
            {
                $guide_image_special			= $this->config->item('guide_image_special'); //주유신규 프로세스적용 특별매장리스트
                $guide_image_normal				= $this->config->item('guide_image_normal'); //기본이미지
                $guide_image_dp2				= $this->config->item('guide_image_dp2'); //dp2

                $guide_image_dp1_self			= $this->config->item('guide_image_dp1_self'); //기본이미지
                $guide_image_dp1_full			= $this->config->item('guide_image_dp1_full'); //기본이미지
                $guide_image_dp2_self			= $this->config->item('guide_image_dp2_self'); //기본이미지
                $guide_image_dp2_full			= $this->config->item('guide_image_dp2_full'); //기본이미지

                if($yn_dp2_install == "Y") // DP2
                {
                    if($yn_self =="Y") $guide_image = $guide_image_dp2_self;
                    else $guide_image = $guide_image_dp2_full;
                }
                else // DP1
                {
                    if($yn_self =="Y") $guide_image = $guide_image_dp1_self;
                    else $guide_image = $guide_image_dp1_full;
                }

                $guide_image_preset					= $this->config->item('guide_image_preset'); //dp2


                if($yn_preset == "N") // 주유주문 예약상태
                {
                    $action_guide_main_msg	= "원하시는 시간에 주유소에 방문해 주세요.";

                    if($yn_self == "Y") // 셀프주유소
                    {
                        $action_guide_sub_msg = "주유하실 주유기에 정차 후 앱을 실행 시켜 주세요.";
                    }
                    else
                    {
                        $action_guide_sub_msg = "오윈 하드웨어가 설치된 주유기 옆에 정차 후 앱을 실행 시켜 주세요.";
                    }
                }
            }

            $response['CHK_kind']		= $this->cd_biz_kind;


            $response['guide_deatil_image']			= $guide_image;
            $response['guide_preset_image']			= $guide_image_preset; // 번호선택화면 (?)

            $response['action_guide_main_msg']		= $action_guide_main_msg;
            $response['action_guide_sub_msg']		= $action_guide_sub_msg;

            ## ------------------------------------------------------------------------------------
            ## FnB 주문 안내 메세지
            ## ------------------------------------------------------------------------------------

            $action_order_main_msg	= "";
            $action_order_sub_msg	= "";

            if($this->cd_biz_kind != "201300")
            {
                // 주문완료
                if($this->cd_order_process  == "616101")
                {
                    $action_order_main_msg	= "매장에서 주문 접수 대기 중입니다.";
                    $action_order_sub_msg = "매장의 사정으로 주문이 취소될 수 있습니다.\n주문 접수까지는 최대 5분이 소요될 수 있습니다.";
                }

                // 주문처리중
                if($this->cd_order_process == "616201" || $this->cd_order_process == "616301" || $this->cd_order_process == "616401")
                {
                    $action_order_main_msg	= "매장에서 상품 준비 중입니다.";

                    // CarID 보유회원
                    if($yn_device == "Y")
                    {
                        $action_order_sub_msg = "픽업 예약 시간에 맞춰 수령위치로 이동해 주세요.\n수령위치에 도착하면 매장에 자동으로 도착을 안내합니다.";
                    }
                    else
                    {
                        $action_order_sub_msg = "픽업 예약 시간에 맞춰 수령위치로 이동해 주세요.\n수령위치에 도착하면 도착알림 버튼을 눌러주세요.";
                    }
                }

                // 전달완료
                if($this->cd_order_process  == "616501")
                {
                    $action_order_main_msg	= "상품 전달이 완료되었습니다.";
                    $action_order_sub_msg = "전달받은 상품에 만족 하시나요?\n의견을 리뷰로 남겨주세요.";
                }

                // 취소
                if($this->cd_order_process  == "616991")
                {
                    $action_order_main_msg	= "주문이 취소되었습니다..";
                    $action_order_sub_msg = "주문취소로 결제가 취소되었습니다.";
                }

            }

            $response['action_order_main_msg']		= $action_order_main_msg;
            $response['action_order_sub_msg']		= $action_order_sub_msg;


            ## 주유혜택-세차쿠폰 정보 반환
            $benefit_coupon_info= $this->event_mdl->get_benefit_coupon_info();
            if(count($benefit_coupon_info) > 0 )
            {
                $use_benefit_coupon_yn = $benefit_coupon_info['use_coupon_yn'];
                $use_benefit_coupon_no = $benefit_coupon_info['no_benefit'];
            }else{
                $use_benefit_coupon_yn = "A";
                $use_benefit_coupon_no = "";
            }
            $response['use_benefit_coupon_yn'] =  $use_benefit_coupon_yn;
            $response['use_benefit_coupon_no'] =  $use_benefit_coupon_no;

            // 정상처리 xx
            $response  = set_success_param($response);
        }
        ##6 응답
        process_close($request,$response,TRUE,$add_log);
        exit;
    }


    /**
     * 주문 카운트
     */
    public function history_cnt()
    {
        ##1 요청파라미터 셋팅
        $request = process_init();

        ##2 필수 파라미터 체크
        $required = array('ds_login','no_user');
        empty_param_chk($request,$required);

        ##3 파라미터 셋팅
        $this->ds_login			= $request['ds_login'];			// 로그인정보
        $this->no_user			= $request['no_user'];			// 회원번호

        ##4 권한인증 체크 0200
        authorize($request);

        ##5-1 응답문셋팅::파라미터체크 0300
        $response = array();
        if(false) // 결제방식이 올바른지 체크
        {
        }
        else
        {
            // 디비연결
            $this->slave_db = $this->load->database('slave', TRUE);
            $this->load->model('order_mdl');

            // 주문정보
            $order_cnt		= $this->order_mdl->get_order_cnt();
            $response['order_cnt']			=	$order_cnt;

            // 주문내역의 위경도리스트 (feat.김상우)
            if ($order_cnt > 0) {
                $order_cnt_list		= $this->order_mdl->get_order_cnt_list();
                //$response['order_cnt_list']			=	$order_cnt_list;
            }

            // 정상처리 xx
            $response  = set_success_param($response);
        }

        ##6 응답
        process_close($request,$response,TRUE,$add_log);
    }

    /**
     * 주유주문건 상태 [ver1] - 주문건 처리중 최근처리상태값 전달 [QR 개발]
     */
    public function order_process()
    {
        ##1 요청파라미터 셋팅
        $request = process_init();

        ##2 필수 파라미터 체크
        $required = array('ds_login','no_user','no_order');
        empty_param_chk($request,$required);

        ##3 파라미터 셋팅
        $this->ds_login			= $request['ds_login'];			// 로그인정보
        $this->no_user			= $request['no_user'];			// 회원번호
        $this->no_order			= $request['no_order'];			// 주문번호

        ##4 권한인증 체크 0200
        authorize($request);

        ##5-1 응답문셋팅::파라미터체크 0300
        $response = array();
        if(false) // 결제방식이 올바른지 체크
        {
        }
        else
        {
            // 디비연결
            $this->slave_db = $this->load->database('slave', TRUE);
            $this->load->model('order_mdl');
            $this->load->model('oil_mdl');


            ## 주문정보
            $order_info = $this->order_mdl->get_order_price_info();
            $this->no_shop = $order_info['no_shop'];			// 매장번호

            // 취소 종료 주문건의 경우 에러코드
            if($order_info['cd_order_status'] > 601200 || $order_info['cd_pickup_status'] > 602400 )
            {
                $response['result']				= '0';
                $response['errcode']			= 'E0400';
                $response['errcode_detail']		= 'P2401';
                $response['ip']					= substr($_SERVER['SERVER_ADDR'],-3);
                $response['pid']				= PROCESS_NUMBER;
                $response['result_error_msg']	= "취소된 주문정보입니다.";

                ##6 응답
                process_close($request,$response,TRUE,$add_log);
                exit;
            }

            // 주문정보가 없을 경우 에러코드
            if(!$order_info)
            {
                $response	=  set_fail_param('E0400','P2120'); // 주문정보가 올바르지 않습니다.
            }
            else // 주문 처리정보 (진행상황) 전달
            {
                $this->cd_order_process			= $this->order_mdl->get_order_process();

                $add_err_cd_process = array('616930','616950','616990');
                if(in_array($this->cd_order_process, $add_err_cd_process))
                {
                    $this->cd_order_process = "616100";
                }

                $response['cd_order_process']	= (!$this->cd_order_process)? "0" : $this->cd_order_process; //상태코드가 없을경우 0


                if($this->cd_order_process == '616500' && $order_info['yn_cpn_use']!='Y')
                {
                    $at_cpn_disct = "0";
                }
                else
                {
                    $at_cpn_disct = $order_info['at_cpn_disct'];
                }

                $response['no_shop']		= $this->no_shop;			// 유종

                // 주문건 가격정보 (주유)
                $response['cd_gas_kind']		= $order_info['cd_gas_kind'];			// 유종
                $response['at_liter_gas']		= $order_info['at_liter_gas'];			// 예약주유량
                $response['at_price']			= $order_info['at_price'];				// 예약금액
                $response['at_cpn_disct']		= $at_cpn_disct;						// 쿠폰할인금액
                $response['at_price_real_gas']	= $order_info['at_price_real_gas'];		// 실주유금액
                $response['at_point_disct']		= $order_info['at_point_disct'];		// 현장할인금액
                $response['at_price_pg']		= $order_info['at_price_pg'];			// PG결제금액
                $response['at_liter_real']		= $order_info['at_liter_real'];			// 실주유량
                $response['at_gas_price']		= $order_info['at_gas_price'];			// 유종단가
                $response['yn_cpn_use']			= ($order_info['yn_cpn_use']=='Y')?"Y":"N";			// 쿠폰결제사용	성공여부

                ## ---------------------------------------------------------------------------------
                ## [S] 주유시작버튼 활성화 여부
                ## ---------------------------------------------------------------------------------
                $yn_fueling = "N";

                // 주유카인정보 확인 / 현재 주문건 상태  활성화상태 포함 여부
                // 예약 :: 이전주문 종료 > CarIN > 현주문시간이전
                // 현장 :: 현주문시간 이후 CarIn

                $this->order_dt_reg			= "";
                $this->prev_order_dt_reg	= "";
                $this->join_carid_cnt		= 0;

                // 주유시작 버튼 활성화 상태 (주문시도,접수,준비완료,주유이탈,주유소취소)
                $arr_startable_status = array('602100','602200','602300','602510','602520');

                // 현재주문시간
                $this->order_dt_reg = $order_info['dt_reg'];

                //이전주문정보
                $prev_order_info				= $this->order_mdl->get_prev_order_info();
                $this->prev_order_dt_reg		= $prev_order_info['dt_pickup_status'];
                $today_first					= date("Y-m-d")." 00:00:00";
                $this->prev_order_dt_reg		= (!$this->prev_order_dt_reg)?$today_first:$this->prev_order_dt_reg;


                // 주문건 관련 CarIn 정보
                $join_carid_info = $this->order_mdl->get_join_carid_info();
                $this->join_carid_cnt			= count($join_carid_info);

                if($this->join_carid_cnt > 0)
                {
                    $yn_fueling = "Y";
                }

                $response['order_dt_reg']				= $this->order_dt_reg;
                $response['prev_order_dt_reg']			= $this->prev_order_dt_reg;
                $response['cd_pickup_status']			= $order_info['cd_pickup_status'];		// 유종

                $response['CHK__fueling_log_cnt']		= $fueling_log_cnt;
                $response['CHK__fueling_log_yn_is_in']	= $fueling_log_list[0]['yn_is_in'];
                $response['CHK__join_carid_cnt']		= $this->join_carid_cnt;

                $yn_fueling = "Y"; // 테스트고정
                $response['yn_fueling']					= $yn_fueling;							// 시작버튼 활성화 여부

                ## 주유 DP2.0 설치여부 - 매장기준
                //$dp2_oil_list				= $this->config->item('dp2_oil_list');
                //$yn_dp2_install				= (in_array($this->no_shop , $dp2_oil_list)==true)?"Y":"N";
                //$yn_dp2_install = "Y";
                //$response['yn_dp2_install']	=	$yn_dp2_install;


                ## 주유 DP2.0 설치여부 - 매장기준
                $yn_dp2_install = "N";
                $shop_oil_info					= $this->oil_mdl->get_oil_shop_bill_info();
                $this->yn_dp2_install			= $shop_oil_info['yn_dp2'];
                $response['yn_dp2_install']		= $this->yn_dp2_install;



                ## ---------------------------------------------------------------------------------
                ## [E] 주유시작버튼 활성화 여부
                ## ---------------------------------------------------------------------------------

            }

            // 정상처리
            $response  = set_success_param($response);
        }

        ##6 응답
        process_close($request,$response,TRUE,$add_log);
    }

    /**
     * 주문 요청정보
     */
    public function init()
    {
        ##1 요청파라미터 셋팅
        $request = process_init();

        ##2 필수 파라미터 체크
        $required = array('ds_login','no_shop','no_user','cd_service','at_price_total');
        empty_param_chk($request,$required);

        ##3 파라미터 셋팅
        $this->ds_login			= $request['ds_login'];			// 로그인정보
        $this->cd_service		= $request['cd_service'];		// 서비스구분
        $this->no_user			= $request['no_user'];			// 회원번호
        $this->no_shop			= $request['no_shop'];			// 매장번호
        $this->at_price_total	= $request['at_price_total'];	// 결제총액
        $this->list_product		= $request['list_product'];		// 주문상품리스트

        ##4 권한인증 체크 0200
        authorize($request);

        ##5-1 응답문셋팅::파라미터체크 0300
        $response = array();
        if(false) // 결제방식이 올바른지 체크
        {
        }
        else
        {
            // 디비연결
            $this->slave_db = $this->load->database('slave', TRUE);
            $this->load->model('order_mdl');
            $this->load->model('member_mdl');
            $this->load->model('card_mdl');
            $this->load->model('coupon_mdl');
            $this->load->model('shop_mdl');
            $this->load->model('credit_mdl'); // [OTC개발]

            // 회원차량정보 반환
            $carinfo = $this->member_mdl->get_member_carinfo();
            if(!$carinfo['seq'])
            {
                $response['result']				= '0';
                $response['errcode']			= 'E0400';
                $response['errcode_detail']		= 'PA141';
                $response['ip']					= substr($_SERVER['SERVER_ADDR'],-3);
                $response['pid']				= PROCESS_NUMBER;
                $response['result_error_msg']	= '차량정보 등록후 주문이 가능합니다.';
                process_close($request,$response,TRUE,$add_log);
                exit;
            }


            // 브랜드 및 상점명
            $partner = $this->order_mdl->get_info_partner($this->no_shop);
            $this->no_partner = $partner['no_partner'];

            // 매장정보 [픽업위치 - 매장위경도 추가]  [2019-03-29]
            $shop_info = $this->shop_mdl->get_info();
            $response['at_lat']		= $shop_info['at_lat'];
            $response['at_lng']		= $shop_info['at_lng'];


            //매장휴일 정보
            $shop_holiday = $this->shop_mdl->get_shop_holiday();


            // 매장영업시간 정보
            $shop_opt_time = $this->shop_mdl->get_info_opt_time();

            $response['nm_partner']			= $partner['nm_partner'];
            $response['nm_shop']			= $partner['nm_shop'];

            //$response['Org_ds_open_time']		= $partner['ds_open_time'];
            //$response['Org_ds_close_time']		= $partner['ds_close_time'];

            if($shop_holiday['hoday100']==0 && $shop_holiday['hoday200']==0 &&$shop_holiday['hoday300']==0 &&$shop_holiday['hoday400']==0 &&$shop_holiday['hoday500']==0 &&$shop_holiday['hoday600']==0 &&$shop_holiday['hoday900']==0)
            {
                $yn_open		= 'Y';			// 매장 open
                $ds_open_time	=  date( "Hi", strtotime( "+".$partner['at_make_ready_time']." minutes" ,strtotime($shop_opt_time['ds_open_time'])) );
                $ds_close_time	=  date( "Hi", strtotime( "-30 minutes" ,strtotime( $shop_opt_time['ds_close_time'])) );

            }else{
                if($shop_holiday['hoday900'] > 0 ){
                    $yn_open		= 'T';		// 매장임시휴일
                    $ds_open_time	=  date( "Hi", strtotime( "+".$partner['at_make_ready_time']." minutes" ,strtotime($shop_opt_time['ds_open_time'])) );
                    $ds_close_time	=  date( "Hi", strtotime( "-30 minutes" ,strtotime( $shop_opt_time['ds_close_time'])) );
                }else{
                    $yn_open		= 'N';		// 매장 close
                    $ds_open_time	= "0400";
                    $ds_close_time	= "0401";
                }
            }

            // 매장 POS(포스) 오류상태 정보 [2018-05-10] [업데이트 예정]
            $shop_pos_error = $this->shop_mdl->get_shop_pos_error();

            // 포스오류 5분이상 지속시 - 매장 오픈준비중 상태 전환
            if(count($shop_pos_error) > 0)
            {
                if($shop_pos_error['cd_ark_status']  == '304900' && $shop_pos_error['alert_time_diff'] > 5)
                {
                    $yn_open		= 'N';		// 매장 close
                    $ds_open_time	= "1900";
                    $ds_close_time	= "2400";
                }
            }

            $response['yn_open']			= $yn_open;
            $response['shop_holiday']		= $shop_holiday;
            $response['at_make_ready_time']	= $partner['at_make_ready_time'];
            $response['ds_open_time']		= $ds_open_time;
            $response['ds_close_time']		= $ds_close_time;

            // 브레이크타임 추가 [2018-04-10]

            $now_time = date("Hi");
            $cd_break_time			= [];
            $ds_break_start_time	= [];
            $ds_break_end_time		= [];

            $break_time[0]["cd_break_time"]			= ($shop_opt_time['cd_break_time'])?$shop_opt_time['cd_break_time']:0;
            $break_time[0]["ds_break_start_time"]	= $shop_opt_time['ds_break_start_time'];
            $break_time[0]["ds_break_end_time"]		= $shop_opt_time['ds_break_end_time'];

            $break_time[1]["cd_break_time"]			= ($shop_opt_time['cd_break_time2'])?$shop_opt_time['cd_break_time2']:0;
            $break_time[1]["ds_break_start_time"]	= $shop_opt_time['ds_break_start_time2'];
            $break_time[1]["ds_break_end_time"]		= $shop_opt_time['ds_break_end_time2'];
            $response['break_time']					= $break_time;

            ## ----------------------------------------------------------------------------------
            ## 회원가입시 핸드폰 국가확인 - 태국시연용
            ## ----------------------------------------------------------------------------------
            $member_info		= 	$this->member_mdl->get_user_info_all();
            $user_lang			= ($member_info['ds_nation']=="en")?"en":"kr";

            ## ----------------------------------------------------------------------------------
            ## 외부연동 회원구분 [현대POC]
            ## ----------------------------------------------------------------------------------

            $this->cd_third_party	= $member_info['cd_third_party'];
            $this->cd_mem_level		= $member_info['cd_mem_level'];
            if($this->cd_mem_level == "104500")
            {
                $this->cd_third_party	= "110100";
            }

            ## ---------------------------------------------------------------------------
            ## [국민카드] - 국민카드로만 주유주문 (결제)
            ## ---------------------------------------------------------------------------

            // 유의사항
            if($user_lang == "en")
            {
                $response['ds_common_notice']	= $this->config->item('ds_common_notice_en');	// 공통유의사항
            }else{
                $response['ds_common_notice']	= $this->config->item('ds_common_notice');	// 공통유의사항
            }
            $response['ds_partner_notice']	= '';//$partner['ds_shop_notice'];			// 브랜드별 유의사항
            $response['ds_shop_notice']		= '';//$shop['ds_shop_notice'];				// 매장별 유의사항

            ## ---------------------------------------------------------------------------
            ## 등록한 카드리스트 [OTC개발]
            ## ---------------------------------------------------------------------------
            // 등록한 카드리스트
            //$this->list_card = $this->card_mdl->get_card_list();
            //$response['list_card']		=	$this->list_card;

            ## 빌키결제  PG 정보 - KCP 500600  NICE 500600
            $this->list_cd_pg = array('500600' ,'500700');

            ## 7-1. 결제 카드리스트 - Billkdy + NICE  (Sort: NICE > 등록일 순)
            $list_card  = $this->credit_mdl->get_order_card_list();  // 결제 가능카드 내려줌
            $response['list_card']		    = $list_card;
            $add_log['list_cd_pg']		    = @implode(' , ',$this->list_cd_pg);
            ## ---------------------------------------------------------------------------

            // 매장할인가격 조회
            $shop_discount = 0;
            if(count($this->list_product) > 0)
            {
                foreach($this->list_product as $key=>$val)
                {
                    $no_product[]	= $val['no_product'];
                }
                $no_product_implode = "'".implode("','",$no_product)."'";
                $shop_discount      = $this->order_mdl->get_shop_discount($no_product_implode);
            }
            $response['at_shop_discount']	= (int)$shop_discount;

            // 사용가능한 오윈캐쉬
            $user_cash		=  $this->member_mdl->get_user_cash();
            $user_cash_sum	=  $user_cash['at_cash'] + $user_cash['at_event_cash'];
            $at_owin_cash	=	($user_cash_sum) ? $user_cash_sum : 0;
            $response['at_owin_cash']	= (int)$at_owin_cash;

            // 쿠폰조건
            $coupon_order['no_partner']		= $this->no_partner;
            $coupon_order['no_shop']		= $this->no_shop;
            $coupon_order['list_card']		= $this->list_card;
            $coupon_order['list_product']	= $this->list_product;
            $coupon_order['at_price_total'] = $this->at_price_total;
            $add_log['coupon_order'] = $coupon_order;
            $add_log['no_product'] = $no_product;

            $coupon = $this->coupon_mdl->get_user_coupon($coupon_order,$no_product);
            $response['list_coupon']	  =	$coupon;

            $list_coupon_sort = arr_sort($coupon,'at_discount','desc');
            /*
				$coupon_sort		= "";
				$list_coupon_sort	= "";

				//사용가능한 추천쿠폰
				if(count($coupon) > 0)
				{
					//foreach($coupon as $k=>$v)
					//{
						$coupon_sort[$v['at_discount']] = $v;
					//}
					//krsort($coupon_sort,SORT_NUMERIC);
					arsort($coupon,SORT_NUMERIC);


				}
				$response['coupon_sort']	  =	$coupon_sort;
				if(count($coupon_sort) > 0)
				{
					$n=0;
					foreach($coupon_sort as $k=>$v)
					{
						$list_coupon_sort[$n] = $v;
						$n++;
					}
				}
			*/

            $response['list_coupon']	  =	$list_coupon_sort;
            $response['list_coupon_vote'] = $this->coupon_mdl->get_user_coupon_vote($coupon);

            // 추가 반환정보
            if($this->cd_service == '900100') // 픽업
            {
                // 디비에서 당일 예약중인 주문번호와 픽업시간대를 가져온다.
                $impossible = $this->order_mdl->get_today_picktime($this->no_shop);

                $response['ds_impossible'] = $impossible; //--테스트중 적용
                //$response['ds_impossible'] = array();
            }

            // 정상처리
            $response  = set_success_param($response);
        }
        ##6 응답
        process_close($request,$response,TRUE,$add_log);
    }


    /**
     * 결제요청 [OTC개발]
     */
    public function payment()
    {

        ##1 요청파라미터 셋팅
        $request = process_init();


        ##2 필수 파라미터 체크
        $required = array('ds_login','no_user','cd_service','no_shop','cd_payment','at_price_total','ct_product');
        empty_param_chk($request,$required);

        ##3 파라미터 셋팅
        $this->ds_login			= $request['ds_login'];													// 로그인정보
        $this->cd_service		= $request['cd_service'];												// 서비스구분
        $this->cd_service_pay	= $request['cd_service_pay'] ? $request['cd_service_pay'] : '901100';	// 결제기능구분 901100 일반방식, 901100 러쉬결제

        $this->no_user			= $request['no_user'];											// 유저고유번호
        $this->no_shop			= $request['no_shop'];											// 매장번호
        $this->no_card			= ($request['no_card']) ? $request['no_card'] : '0';			// 카드등록 관리번호 - 카드등록 결제일경우만 전송

        $this->cd_payment		= $request['cd_payment'];										// 결제방식 (501100 일반결제, 501200 카드등록결제, 501300 카카오페)
        $this->cd_payment_kind	= $request['cd_payment_kind'];									// 결제방식 (502100 신용카드, 502200 휴대폰)
        $this->cd_payment_status = 603100;														// 결제요청

        $this->cd_payment_method	= $request['cd_payment_method'];					        // 결제방법 (504100 BillKey결제, 504200 OTC결제)

        // [OTC개발] 필수값 세팅 TEST
        if(!$this->cd_payment_method) $this->cd_payment_method  =  "504100" ;

        $this->at_price_total 	= $request['at_price_total'];									// 총결제금액
        $this->list_no_event	= $request['list_no_event'];									// 사용할 이벤트 번호(쿠폰번호)
        $this->at_cpn_disct		= ($request['at_cpn_disct'])? $request['at_cpn_disct']:'0';										// 쿠폰 할인 총금액
        $this->at_owin_cash		= $request['at_owin_cash'];										// 주문번호

        $this->dt_pickup		= $request['dt_pickup'];										// 픽업예상시간 (ex 2016-02-23 23:55:00 )
        $this->at_lat_decide	= ($request['at_lat_decide']) ? $request['at_lat_decide'] : '0';// 목적지 위도
        $this->at_lng_decide	= ($request['at_lng_decide']) ? $request['at_lng_decide'] : '0';// 목적지 경도

        $this->ct_product		= $request['ct_product'];										// 주문상품갯수
        $this->list_product		= $request['list_product'];										// 주문상품리스트
        $this->no_order			= $request['no_order'];											// 주문번호(재결재시도)

        $this->at_lat			= ($request['at_lat']) ? $request['at_lat_decide'] : '0';		// 현재 위도
        $this->at_lng			= ($request['at_lng']) ? $request['at_lng'] : '0';				//현재 경도

        $this->yn_gps_status	= ($request['yn_gps_status'] == 'Y') ? 'Y' : 'N';				// GPS 활성화 여부
        $this->is_retry_order   = ($this->no_order) ? TRUE : FALSE;								// 주문번호를 이미 생성한경우 - ex) 결제 실패후 재결제

        $this->ds_request_msg	= $request['ds_request_msg'];											// 주문요청시메세지


        ##4 권한인증 체크 0200
        authorize($request);

        ##5-1 응답문셋팅::파라미터체크 0300
        $response = array();

        ##6 응답 make :: chomink [2017-05-02]

        //if(!IS_TEST_SERVER)
        //{
        //	$response =  set_fail_param('E0300','데모버전은 결제가 되지 않습니다.');
        //	process_close($request,$response,TRUE,$add_log);
        //	exit;
        //}

        if(un_valid_cd($this->cd_service,'900'))// 서비스구분
        {
            $response	=  set_fail_param('E0300','C0900');
        }
        else if(un_valid_cd($this->cd_payment,'501'))// 결제방식 구분
        {
            $response	=  set_fail_param('E0300','C0501');
        }
        else if(un_valid_date($this->dt_pickup))// 픽업시간형식
        {
            $response	=  set_fail_param('E0300','C3110');
        }
        else if(un_valid_float($this->at_price_total))// 결제금액형식
        {
            $response	=  set_fail_param('E0300','C3120');
        }
        else
        {
            #-----------------------------------------
            # 결제 vaild
            #-----------------------------------------

            // 디비연결
            $this->master_db = $this->load->database('master', TRUE);
            $this->slave_db = $this->load->database('slave', TRUE);
            $this->load->model('member_mdl');
            $this->load->model('card_mdl');
            $this->load->model('order_mdl');
            $this->load->model('payment_mdl');
            $this->load->model('beacon_mdl');
            $this->load->model('coupon_mdl');
            $this->load->model('cash_mdl');
            $this->load->model('shop_mdl');
            $this->load->model('search_mdl');
            $this->load->model('admin_order_mdl');  // 자동주문
            $this->load->model('credit_mdl');       // OTC개발


            ## 회원정보 - 기본
            $member = $this->member_mdl->get_user_info_all();

            ## ----------------------------------------------------------------------------------
            ## 회원가입시 핸드폰 국가확인 - 태국시연용
            ## ----------------------------------------------------------------------------------
            $user_lang		= ($member['ds_nation']=="en")?"en":"kr";
            $id_user		= $member['id_user'];

            ## ----------------------------------------------------------------------------------
            ## 오윈테스트어드민의 경우 - 기기오류매장 정상주문
            ## ----------------------------------------------------------------------------------
            $admin_member	= unserialize(ADMIN_MEMBER);
            $this->owin_admin = (in_array($id_user, $admin_member , true))?true:false;

            ## 매장정보 - 기본
            $shopinfo				= $this->shop_mdl->get_info();
            $list_cd_booking_type	= explode(",",$shopinfo['list_cd_booking_type']); // 매장주문가능방식

            ## CarID정보 확인 - 미등록회원의 경우 주문불가
            $beacon = $this->beacon_mdl->get_my_beacon();
            $this->no_device			= $beacon[0]['no_device'];
            $this->ds_adver				= $beacon[0]['ds_adver'];

            // CHECK 0. 주문방식에 CarID 주문만 있는경우 에만 등록된 CarID체크
            if(count($list_cd_booking_type) > 0)
            {
                if($shopinfo['list_cd_booking_type'] == "505100")
                {

                    if(count($beacon) < 1)
                    {
                        $response['result']				= '0';
                        $response['errcode']			= 'E0400';
                        $response['errcode_detail']		= 'PA140';
                        $response['ip']					= substr($_SERVER['SERVER_ADDR'],-3);
                        $response['pid']				= PROCESS_NUMBER;
                        $response['result_error_msg']	= 'CarId등록후 주문이 가능합니다.';
                        process_close($request,$response,TRUE,$add_log);
                        exit;
                    }
                }
            }
            // 5분안에 20개 이상 주문건이 있는지 확인용
            $ct_pickup = 0;

            ## CHECK 1. 매장정보 영업정보

            // 매장휴일정보 - 픽업시간 기준 (임시휴일 시간적용 - 시간체크)
            $this->dt_pickup_day = $this->dt_pickup;
            $shop_holiday = $this->shop_mdl->get_pickup_shop_holiday();

            if($shop_holiday['hoday100']==0 && $shop_holiday['hoday200']==0 &&$shop_holiday['hoday300']==0 &&$shop_holiday['hoday400']==0 &&$shop_holiday['hoday500']==0 &&$shop_holiday['hoday600']==0 &&$shop_holiday['hoday900']==0)
            {
                $yn_open		= 'Y';			// 매장 open
            }else{
                if($shop_holiday['hoday900'] > 0 ){
                    $yn_open		= 'T';		// 매장임시휴일
                    $errcode_detail = "P2054";
                    $result_error_msg = "오픈 준비 중입니다.";
                }else{
                    $yn_open		= 'N';		// 매장 close
                    $errcode_detail = "P2055";
                    $result_error_msg = "매장 휴일입니다.";
                }
            }

            # CHECK 2. 현재 아크/POS 상태가 올바른지 체크

            // 포스오류 매장 리스트 [업데이트예정]
            // 5분간 포스오류인 매장의 경우 오픈준비중노출
            $this->array_pos_error = $this->search_mdl->get_pos_error_list();

            ## -  POS 오류매장 확인		- 오픈준비중입니다. 노출
            ## 06:00 ~ 오픈시간까지		= 오픈중비중입니다.
            ## 오픈시간이후				= 영업이 종료되었습니다.

            // [태국시연버전 , 오윈어드민계정] 제외
            if(!$this->owin_admin && $user_lang != "en")
            {
                if(in_array($this->no_shop , $this->array_pos_error , true))
                {
                    $yn_open		= 'T';		// 매장임시휴일
                    $errcode_detail = "P2054";
                    $result_error_msg = "오픈 준비 중입니다.";
                }
            }

            $add_log["yn_open"]         = $yn_open;
            $add_log["shop_holiday"]    = @implode(',', $shop_holiday);

            # CHECK 3. 브레이크타임 추가 [업데이트 예정2018-04-10]

            // 매장영업시간
            $shop_opt_time = $this->shop_mdl->get_info_opt_time();

            $pickup_time = date('Hi', strtotime($this->dt_pickup));	 // 픽업설정 시간
            $order_time = date('Hi'); // 주문시간


            // 주문시간이 영업시간 이외일 경우 주문 불가
            if($shop_opt_time['ds_open_time'] > $order_time ||  $shop_opt_time['ds_close_time'] <  $order_time )
            {
                $yn_open		= 'T';
                $errcode_detail = "P2054";
                $result_error_msg = "현재 매장 영업시간이 아닙니다.";
            }

            // 에러메세지가 있을경우 에러코드 전달
            if($result_error_msg)
            {
                $response['result']				= '0';
                $response['errcode']			= 'E0400';
                $response['errcode_detail']		= $errcode_detail;
                $response['ip']					= substr($_SERVER['SERVER_ADDR'],-3);
                $response['pid']				= PROCESS_NUMBER;
                $response['result_error_msg']	= $result_error_msg;
                process_close($request,$response,TRUE,$add_log);
                exit;
            }

            $cd_break_time			= 0;
            $ds_break_start_time	= "";
            $ds_break_end_time		= "";

            if($shop_opt_time['ds_break_start_time'] <= $pickup_time && $shop_opt_time['ds_break_end_time'] >=  $pickup_time )
            {
                $cd_break_time			= $shop_opt_time['cd_break_time'];
                $ds_break_start_time	= $shop_opt_time['ds_break_start_time'];
                $ds_break_end_time		= $shop_opt_time['ds_break_end_time'];
            }

            if($shop_opt_time['ds_break_start_time2'] <= $pickup_time && $shop_opt_time['ds_break_end_time2'] >=  $pickup_time )
            {
                $cd_break_time			= $shop_opt_time['cd_break_time2'];
                $ds_break_start_time	= $shop_opt_time['ds_break_start_time2'];
                $ds_break_end_time		= $shop_opt_time['ds_break_end_time2'];
            }

            # CHECK 4. 브레이크타임 세팅후 메세지 설정
            $ds_break_start_time_str	= make_timestr($ds_break_start_time);// XX:XXPM 형식
            $ds_break_end_time_str		= make_timestr($ds_break_end_time);

            if($ds_break_start_time <= $pickup_time && $ds_break_end_time >=  $pickup_time )
            {
                if($cd_break_time == 217100)
                {
                    $errcode_detail = "P2053";
                    $result_error_msg = $ds_break_start_time_str."~".$ds_break_end_time_str ." 는\n브레이크 타임입니다.";
                }
                else if($cd_break_time == 217200)
                {
                    $errcode_detail = "P2052";
                    $result_error_msg = $ds_break_start_time_str."~".$ds_break_end_time_str ." 는 \n매장이 혼잡한 시간입니다.";
                }

                $response['result']				= '0';
                $response['errcode']			= 'E0400';
                $response['errcode_detail']		= $errcode_detail;
                $response['ip']					= substr($_SERVER['SERVER_ADDR'],-3);
                $response['pid']				= PROCESS_NUMBER;
                $response['result_error_msg']	= $result_error_msg;
                process_close($request,$response,TRUE,$add_log);
                exit;
            }

            # CHECK 4-1. 핍업시간 < 현재시간+준비시간 = error  >> 픽업시간 체크  -----
            if($user_lang != "en")
            {
                $partner            = $this->order_mdl->get_info_partner($this->no_shop);
                $pickup_chk_time	=  date( "Hi", strtotime( "+".$partner['at_make_ready_time']." minutes")); // 리얼 최소 준비시간

                if($pickup_time < $pickup_chk_time)
                {

                    $output  = "\n#".date('Y-m-d H:i:s')."\t Order_Payment_Timeover_ERROR";
                    $output .= "\t# [shop] ".$this->no_shop;
                    $output .= "\t# [pickup_time] ".$pickup_time;
                    $output .= "\t# [pickup_chk_time] ".$pickup_chk_time;
                    $output .= "\t# [at_make_ready_time] ".$partner['at_make_ready_time']."---------#\n";

                    write_file('/private_data/owinlog/'.date('Ymd').'/fnb_confirm_log', $output ,FOPEN_READ_WRITE_CREATE);


                    $response	=  set_fail_param('E0400','P2050');
                }
            }

            # --------------------------------------------------------------------------------------------
            # Pickup Time 업데이트 설정시간
            # --------------------------------------------------------------------------------------------
            $this->at_make_ready_time			= (INT)$shopinfo['at_make_ready_time']; //메뉴최소준비시간
            $this->pickup_update_time			= PICKUP_UPDATE_TIME; // 업데이트 설정시간

            # CHECK 4-2. 픽업서비스일경우만 불가능한 픽업시간 전달
            if($this->cd_service == '900100')
            {
                $picktime_start = $this->dt_pickup;
                $date_arr		= date_parse($this->dt_pickup);
                $timestemp		= mktime($date_arr['hour'],$date_arr['minute'],$date_arr['second'],$date_arr['month'],$date_arr['day'],$date_arr['year']);
                $picktime_end   =  date('Y-m-d H:i:s',strtotime("+5 minute", $timestemp));
                $ct_pickup		= $this->order_mdl->yn_picktime($picktime_start,$picktime_end);

                $hour_min = $date_arr['hour'].$date_arr['minute'];

                // 디비에서 당일 예약중인 주문번호와 픽업시간대를 가져온다.
                $impossible = $this->order_mdl->get_today_picktime($this->no_shop);

                if(count($impossible) > 0)
                {
                    foreach($impossible as $key=>$val)
                    {
                        $impossible_time[] = $val['ds_time'];
                    }
                }
                //$add_log[impossible] = $impossible_time;
                //$add_log[hour_min] = $hour_min;
            }

            # CHECK 4-3. 픽업시간이 올바른지 체크
            if(count($this->list_product) == 0) // CHECK 2-1. 주문상품정보가 없는 경우
            {
                $response	=  set_fail_param('E0400','P2040');
            }
            else if($this->cd_service == '900100' and  date('Y-m-d H:i:s') > $this->dt_pickup ) // CHECK 2-2. 이미 지난시간이면
            {
                if($user_lang != "en") // [태국시연버전] - 로컬시간 오류로 임시 정지
                {
                    $response	=  set_fail_param('E0400','P2050');
                }
            }
            else if($this->cd_service == '900100' and (is_array($impossible_time) and in_array($hour_min,$impossible_time))) // CHECK 2-3. 픽업불가능한 시간인지 체크
            {
                //$response	=  set_fail_param('E0400','P2050'); --테스트중 chomnk
            }
            else if($hour_min < $shop_opt_time['ds_open_time'] and $hour_min > $shop_opt_time['ds_close_time'] ) // CHECK 2-4. 영업시간인지 확인
            {
                if($user_lang != "en") // [태국시연버전] - 로컬시간 오류로 임시 정지
                {
                    $response	=  set_fail_param('E0400','P2130');
                }
            }

            // 에러코드 셋팅시 종료처리
            if(count($response) > 0)
            {
                $output  = "\n#".date('Y-m-d H:i:s')."\t Order_Payment_ERROR ";
                $output .= " #[shop] ".$this->no_shop;
                $output .= " #[cnt_product] ".count($this->list_product);
                $output .= " #[pickup_time] ".$pickup_time;
                $output .= " #[pickup_chk_time] ".$pickup_chk_time;
                $output .= " #[make_ime] ".$partner['at_make_ready_time']."\t";
                $output .= " #[ds_open_time] ".$shop_opt_time['ds_open_time'];
                $output .= " # [ds_close_time] ".$shop_opt_time['ds_close_time']."---------#\n";

                write_file('/private_data/owinlog/'.date('Ymd').'/fnb_confirm_log', $output ,FOPEN_READ_WRITE_CREATE);

                ##6 응답
                process_close($request,$response,TRUE,$add_log);
                exit;
            }

            ##  국민카드 회원의 경우
            $this->cd_third_party	= $member['cd_third_party']; //104500
            $this->cd_mem_level		= $member['cd_mem_level'];

            // 국민카드 회원
            if($this->cd_mem_level == "104500") {
                $this->cd_third_party	= "110100";
            }

            // RSM회원 > APP주문시 오윈주문처리 
            if($this->cd_mem_level == "104600") {
                $this->cd_third_party = "110000";
            }


            ## -------------------------------------------------------------------------
            # CHECK 5. 결제수단 정보 확인
            ## -------------------------------------------------------------------------

            # CHECK 5-0  cd_pg 정보 / 수수료정보  -----
            $shopinfo		            = $this->order_mdl->get_order_shop();
            $this->cd_pg				= $shopinfo['cd_pg']; // 매장에 설정된 PG정보
            $this->cd_commission_type	= $shopinfo['cd_commission_type']; //수수료방식
            $this->at_commission_rate	= 0; //수수료율

            if($this->cd_commission_type == "205300")
            {
                $this->at_commission_rate = $shopinfo['at_commission_rate'];
            }

            # CHECK 5-1. 카드 등록이 되어 있는지 체크
            if(!$this->no_card || $this->no_card == 0)
            {
                $this->yn_success 	= "N";
                $this->reg_code 	= "P1031";
                $this->reg_msg  	= "'지갑' 메뉴에서 결제카드\n등록 후 이용해주세요.";

                $response['result']				= '0';
                $response['errcode']			= 'E0400';
                $response['errcode_detail']		= $this->reg_code;
                $response['result_error_msg']	= $this->reg_msg;
                $response['pid']				= PROCESS_NUMBER;
                $response['ip']					= substr($_SERVER['SERVER_ADDR'],-3);

                ##6 응답
                process_close($request,$response,TRUE,$add_log);
                exit;
            }

            # CHECK 5-2. 올바른 쿠폰인지 체크
            if(count($this->list_no_event) > 0 && $this->cd_service_pay != '901200') // 쿠폰을 사용한경우 (러쉬제외)
            {
                // 쿠폰조건
                $coupon_order['no_partner']		= $shopinfo['no_partner'];
                $coupon_order['no_shop']		= $this->no_shop;
                $coupon_order['list_card']		= $this->list_card;
                $coupon_order['list_product']	= $this->list_product;
                $coupon_order['at_price_total'] = $this->at_price_total;

                $add_product_coupon = array();	// 품목증정 쿠폰
                $at_cpn_disct =0;				//할인 총액
                $coupon_info_arr = array();

                foreach($this->list_no_event as $key=>$val)
                {
                    if($val == '0')
                    {
                        continue;
                    }
                    # 3-1. 사용할 쿠폰을 조회
                    $coupon_info = $this->coupon_mdl->get_coupon_info($val,$this->no_user);

                    # 3-2. 사용조건
                    $coupon_no_event = array($coupon_info['no_event']);
                    $condition		 = $this->coupon_mdl->get_coupon_condition($coupon_no_event);

                    # 사용조건이 올바른지 체크 $val = > coupon_info
                    $vaild_coupon = $this->coupon_mdl->vaild_coupon_condition($coupon_info,$condition[$coupon_info['no_event']],$coupon_order);

                    if($coupon_info['cd_cpe_status'] != '121100') // 사용중단된 쿠폰
                    {
                        $response	=  set_fail_param('E0400','P2300');
                        break;
                    }
                    else if($coupon_info['cd_mcp_status'] != '122100') // 이미 사용한 쿠폰
                    {
                        $response	=  set_fail_param('E0400','P2310');
                        break;
                    }
                    else if($vaild_coupon['yn_use'] == 'N')
                    {
                        $response	=  set_fail_param('E0400','P2320');
                        break;
                    }

                    # 품목증정인경우
                    if($coupon_info['cd_disc_type'] == '126300')
                    {
                        $add_product_coupon['no_event']   = $coupon_info['no_event'];
                        $add_product_coupon['no_product'] = $coupon_info['at_discount'];
                    }

                    $at_cpn_disct += $this->coupon_mdl->get_discount_money($coupon_info);
                    $coupon_info_arr[] = $coupon_info;
                }

                ## 3-3. 쿠폰할인 금액 확인 비교
                if($this->at_cpn_disct != $at_cpn_disct) //	쿠폰 총 할인금액
                {
                    //$response	=  set_fail_param('E0400','P2330');
                }
            }


            # CHECK 5-3. 캐쉬를 사용할 수 있는지 체크 후 - 캐시정보 설정
            if($this->at_owin_cash > 0)
            {
                // 현재 보유한 오윈캐쉬 반환
                //$owincash		= $this->member_mdl->get_user_cash();
                $owincash_total = $member['at_cash'] + $member['at_event_cash'];

                // 보유한 캐쉬보다 결제요청 캐쉬가 큰 경우
                if($this->at_owin_cash > $owincash_total)
                {
                    $response	=  set_fail_param('E0400','P2200');
                }
            }

            # CHECK 6. 재결제인데 이전 주문정보가 없는경우 조회
            if($this->is_retry_order)
            {
                $order_list_before = $this->order_mdl->get_order_list_all();
                if(!$order_list_before['no_order'])
                {
                    $response	=  set_fail_param('E0400','P2060');
                }
            }

            # CHECK 7. 장바구니 정보로 주문매장과 상품가격이 올바른지 체크 -- [상품확인]
            foreach($this->list_product as $key=>$val)
            {
                // 단일 배열형태로 변경한다.
                $vaild['no_product'][$key]		= $val['no_product'];
                $vaild['at_price'][$key]		= $val['at_price'];
                $vaild['all'][$key]				= $val;

                // 주문상품가격
                $vaild_price[$val['no_product']]= ($val['at_price_product']/$val['ct_inven']);

                // 상품이 해당 브랜드 상품이 아닌경우
                if(substr($val['no_product'],0,4) != substr($this->no_shop,0,4))
                {
                    $response	=  set_fail_param('E0400','P2070');
                }
            }

            // 픽업주문상품정보 반환 - 중복 상품번호에 다른 옵션이 있을 수 있음
            $pick_product = $this->order_mdl->get_pick_product($vaild);

            // TODO 주문상품 가격정보 검수
            if(count($pick_product) > 0)
            {
                foreach($pick_product as $key=>$val)
                {
                    if($vaild_price[$val['no_product']] != $val['at_price'])
                    {
                        $add_log['PRICE_ERROR1'] = $val['no_product'];
                        $add_log['PRICE_ERROR2'] = $vaild_price[$val['no_product']];
                        $add_log['PRICE_ERROR3'] = $val['no_product'];
                        $add_log['PRICE_ERROR4'] = $val['at_price'];

                        $response	=  set_fail_param('E0400','P2041');
                    }
                }
            }


            ##  RUSH. 러쉬일 경우 카드정보 반환 (러쉬결제방식)
            // [2020-12-21] 러쉬기능 삭제
            // Card - 러쉬일때 request 카드번호 0000 으로 전달받음 : 최우선등록 카드 선택
            // Coupon - 러쉬일 경우 올바른 [쿠폰]인지 체크
            // Cash - 러쉬일 경우 사용자캐시 바로사용 - 캐시정보 설정
            // 빌키결제일때 -1분안에 결제요청한 경우가 있는지 조회 : 중복 주문종료

            // 데이터 로그 -----------------------------------------------------------
            $add_log['CHK_cd_payment_method'] = $this->cd_payment_method;
            $add_log['CHK_cd_mem_level'] = $this->cd_mem_level;
            $add_log['CHK_cd_third_party'] = $this->cd_third_party;
            $add_log['CHK_cd_service_pay'] = $this->cd_service_pay;
            $add_log['CHK_list_no_event'] = $this->list_no_event;
            $add_log['CHK_at_cpn_disct'] = $this->at_cpn_disct;
            // 데이터 로그 -----------------------------------------------------------

            // 에러코드 셋팅시 종료처리
            if(count($response) > 0)
            {
                ##6 응답
                process_close($request,$response,TRUE,$add_log);
                exit;
            }

            #-----------------------------------------
            # 주문정보셋팅
            #-----------------------------------------

            // 브랜드 번호
            $this->no_partner			= substr($this->no_shop,0,4);

            // 결제 총 지불금액
            $this->at_price				= $this->at_price_total;

            // 픽업상태
            $this->cd_pickup_status		= '602100';

            // 회원정보로 현 적립금액 조회
            $this->at_cash_before		= $member['at_cash'];		// 이전 실캐쉬
            $this->at_cash_after		= $member['at_cash'];		// 결제후 실캐쉬
            $this->at_eventcash_before	= $member['at_event_cash'];	// 이전 이벤트 캐쉬
            $this->at_eventcash_after	= $member['at_event_cash'];	// 결제후 이벤트 캐쉬

            // 주문 상품명
            $this->nm_order				= $pick_product[0]['nm_product'];
            if(count($pick_product) > 1)
            {
                if($user_lang == "en")
                {
                    $this->nm_order			.= ' plus '.(count($this->list_product)-1);
                }
                else
                {
                    $this->nm_order			.= ' 외 '.(count($this->list_product)-1).'건';
                }
            }

            // 결제 시도번호 생성
            $this->no_payment			= make_no_payment();

            // 최초 결제인경우  주문번호생성
            if(!$this->is_retry_order)
            {
                $this->no_order			= $this->order_mdl->get_no_order($this->no_shop);
            }



            // 요청카드 카드사 확인 //[OTC개발]  ----------------------------------------------------------------------------
            // no_card 기준으로 빌키정보 조회 - 빌키 내림차순  (NICE > KCP) - 최상위 카드기준 > 결제PG정보 세팅

            $add_log['cd_payment_method']	= $this->cd_payment_method;
            $add_log['shop_cd_pg']		= $this->cd_pg; // 매장설정 PG사 정보

            ## 빌키결제  PG 정보 - KCP 500600  NICE 500700
            //$this->list_cd_pg = array('500600');
            $this->list_cd_pg = array();
            if($this->cd_pg){
                array_push($this->list_cd_pg,  $this->cd_pg); // FnB 매장 PG로 PG결제처리
            }

            $card_info = $this->credit_mdl->get_first_pg_card_info(); // 결제PG기준 최상위 카드 PG정보
            $this->cd_pg  = $card_info['cd_pg'];

            // 매장PG사 - 결제카드PG사 확인로그
            $add_log['card_cd_pg']		    = $this->cd_pg;
            $add_log['card_info']           = $card_info;

            #-----------------------------------------
            # 결제처리 시작
            #-----------------------------------------

            // TODO:: PG 구분처리
            // 할인금액 초기값 추가
            $this->at_disct				= 0;		//	PICK 사용안함 - 오윈상시할인금액
            $this->at_point_disct		= 0;		//	PICK 사용안함 - 브랜드포인트할인금액
            $this->cd_gas_kind			= '';		//	PICK 주유만사용 - 유종종류
            $this->at_gas_price			= 0;		//	PICK 주유만사용 - 유종단가

            $this->at_cash_disct		= 0;		//	캐시할인금액
            $this->at_event_cash_disct  = 0;		//	보너스캐시할인금액
            $this->at_bank_disct		= 0;		//	결재카드사.은행별 할인금액  추가20170123

            // 트랜젝션 시작 ------------------------------------------------------
            $this->master_db->trans_begin();

            ## [빌키결재] 일때만 쿠폰/캐쉬 바로 사용처리

            if($this->cd_payment == '501200')
            {
                $use_shop_name = $shopinfo['nm_partner'].' '.$shopinfo['nm_shop'];

                # 결제 1. 쿠폰사용처리
                self::_coupon_used('Y',$use_shop_name );

                # 결제 2. 캐쉬사용처리
                self::_cash_used($use_shop_name);

                $add_log['use_cash']			= $this->use_cash;
                $add_log['use_event_cash']		= $this->use_event_cash;			// 사용한 이벤트캐쉬
                $add_log['at_cash_after']		= $this->at_cash_after;
                $add_log['at_eventcash_after']	= $this->at_eventcash_after;		// 남은 이벤트 캐쉬
                $add_log['at_cash_disct']		= $this->at_cash_disct;				// 남은 이벤트 캐쉬
                $add_log['at_event_cash_disct'] = $this->at_event_cash_disct;		// 남은 이벤트 캐쉬
            }

            # 결제 3. PG 결제
            $this->at_price_pg	= $this->at_price - ($this->at_disct + $this->at_cpn_disct + $this->at_point_disct + $this->at_owin_cash + $this->at_bank_disct); //	실제pg결제금액


            //매장 건별 수수료작업중
            //건별 수수료매장결제시 PG결제금액에 수수료합산 > order_list 수수료 항목에 수수료추가
            if($this->at_commission_rate > 0)
            {
                $this->at_price_pg = $this->at_price_pg + $this->at_commission_rate;
            }

            $add_log['at_price']			= $this->at_price;
            $add_log['at_price_pg']			= $this->at_price_pg;
            $add_log['at_commission_rate']	= $this->at_commission_rate;

            // 실패
            // PG사 승인시 최소 금액 500원 미만일 경우는 결제 오류
            if(($this->at_price_pg < 500) && ($this->at_price_pg > 0))
            {
                $response['result']         = '0';
                $response['errcode']        = 'E0400';
                $response['errcode_detail'] = 'P2021';
                $response['ip']             = substr($_SERVER['SERVER_ADDR'],-3);
                $response['pid']            = PROCESS_NUMBER;
                $response['result_error_msg']  = "최소 결제 금액은 500원입니다.\n다시 확인해주세요.";

                #응답
                process_close($request,$response,TRUE,$add_log);
                exit;
            }
            
            // 상품개수 [OTC 개발] - 전문추가내용
            $this->product_num = count($this->list_product) ;
            
            // PG로 결제할 금액이 있는경우만
            if($this->at_price_pg > 0)
            {
                ### PG확인 - 결제PG정보 부족
                if(!$this->cd_pg)
                {
                    $response	=  set_fail_param('E0400','P2060');//결제정보가 올바르지않음
                    ##6 응답
                    process_close($request,$response,TRUE,$add_log);
                    exit;
                }

                ### 결제 3-1. [일반결제] --  [사용안함] ----- 빌키결제만 서비스 ##
                if($this->cd_payment == '501100')
                {
                    ##6 일반결제 종료처리 - 응답
                    $response['result']             = '0';
                    $response['errcode']            = 'E0400';
                    $response['errcode_detail']     = 'C0505';
                    $response['ip']                 = substr($_SERVER['SERVER_ADDR'], -3);
                    $response['pid']                = PROCESS_NUMBER;
                    $response['result_error_msg']   = "결제방식 코드가 올바르지 않습니다.\n고객센터로 문의해주세요.";
                    process_close($request,$response,TRUE,$add_log);
                    exit;

                    /*
                        # 결제 3-1-1. 쿠폰사용처리 - 주문번호만 업데이트
                        if(count($coupon_info_arr) > 0 and count($this->list_no_event) > 0)
                        {
                            $this->coupon_mdl->payment('N',$use_shop_name);
                        }

                        # 결재 4-1. PG사 ------ [FDK]
                        if($this->cd_pg == '500100') // FDK 일반결제
                        {
                            $pg_id	= FDK_MXID;
                            self::_payment_501100();

                            // 결제 웹 URL 응답
                            $response['ds_payment_url'] = API_URL.'/payment/request';
                            $response['ds_fd_isp_url']  = 'https://'.FDK_TEST.'pg.firstpay.co.kr/jsp/crdt/m/ispresult.jsp';
                        }

                        # 결재 4-2. PG사 ------ [LGUplus]
                        else if($this->cd_pg == '500200')  // LGU 일반결제
                        {
                            $pg_id	= P_CST_MID;
                            self::_payment_501100();

                            // 결제 웹 URL 응답
                            $response['ds_payment_url'] = API_URL.'/payment/lg_request';
                        }

                        # 결재 4-3. PG사 ------ [Danal]
                        else if($this->cd_pg == '500300') // Danal 일반결제
                        {
                            // Danal 일반결제
                            $pg_id	= DANAL_MID;
                            self::_payment_501100();

                            // 결제 웹 URL 응답
                            $response['ds_payment_url'] = API_URL.'/payment/dn_request';
                        }
                    */
                }

                ### 결제 3-2. [빌키경제]
                else
                {
                    // 결제방법이  BillKey결제 일때
                    if($this->cd_payment_method	== '504100') {

                        ## [step1] 실결제시작

                        ## 러쉬일때 request 카드번호 0000 으로 전달받음
                        ## 최우선등록 카드 선택
                        ## PG - shop 설정된 PG사 정보

                        if ($this->cd_payment == '501200')// 서버에서 처리되는 결제
                        {
                            ## [step2] cd_pg확인후  개인등록 빌키정보 반환

                            # 결재 4-1. PG사 - FDK
                            if ($this->cd_pg == '500100') // FDK 결제
                            {
                                $pg_id = FDK_MXID_BILLKEY;
                                $payment_result = self::_fdk_payment_501200($member);

                                // 등록된 카드 체크카드 여부가 없을 경우 정보업데이트
                                //$add_log['no_card_yn_credit']       = $payment_result['check_param']['yn_credit'];
                                //$add_log['no_card_user_yn_credit']  = $payment_result['check_param']['user_yn_credit'];

                                $this->yn_credit = $payment_result['check_param']['yn_credit'];

                                // 등록된 카드 체크카드 여부가 없을 경우 정보업데이트
                                if (!$payment_result['check_param']['user_yn_credit']) {
                                    $this->card_mdl->update_card_checkyn();
                                }
                            } # 결재 4-2. PG사 - LGUplus
                            else if ($this->cd_pg == '500200') // LGU 빌키결제
                            {
                                $pg_id = P_LGD_MID;//LGD_MID
                                $payment_result = self::_lg_payment_501200($member);

                            } # 결재 4-3. PG사 - Danal
                            else if ($this->cd_pg == '500300') // Danal 빌키결제
                            {
                                // Danal 일반결제
                            } # 결재 4-4. PG사 - KICC
                            else if ($this->cd_pg == '500400') // KICC 빌키결제
                            {
                                // KICC 빌키결제
                                $pg_id = KICC_ID;
                                $payment_result = self::_kicc_payment_501200($member);
                            } # 결재 4-5. PG사 - KSnet
                            else if ($this->cd_pg == '500500') // KSnet 빌키결제
                            {
                                // KSnet 빌키결제
                                $pg_id = KSNET_ID;
                                $payment_result = self::_ksnet_payment_501200($member);
                            } # 결재 4-6. PG사 - KCP
                            else if ($this->cd_pg == '500600') // KCP 빌키결제
                            {
                                // KCP 빌키결제
                                $pg_id = KCP_ID;
                                $payment_result = self::_kcp_payment_501200($member);
                            } # 결제 4-7. PG사 - NICE
                            else if ($this->cd_pg == '500700') // NICE 빌키결제
                            {
                                // NICE 빌키결제
                                $pg_id = NICE_MID;
                                $payment_result = self::_nice_payment_501200($member);
                            }
                        }
                    }
                    // OTC결제 일때 [OTC개발]
                    else
                    {
                        // 주문완료시 결제 정보 전달
                        // msgID				  2  	 GH
                        // noOrder               21   주문번호
                        // cardCompCode           5    카드사 코드
                        // cardID                44   NICE 카드 아이디
                        // partnerMemberID       50   가맹점 회원 아이디
                        // upCorpNum             10   상위 사업자 번호
                        // lowCorpNum            10   하위 사업자 번호
                        // productName           120  상품명
                        // paymentPrice          15   결제요청 금액

                        // 결제매장- 사업자번호 (상위 사업자번호/ 하위 사업자번호)
                        /*
                        $this->up_corp_num			    = "";
                        $this->low_corp_num			    = "";
                        $business_num_info			    = $this->credit_mdl->get_business_num_info();
                        //$this->up_corp_num			= $business_num_info['p_ds_biz_num'];
                        //$this->low_corp_num			= $business_num_info['s_ds_biz_num'];
                        $this->up_corp_num			    = str_replace("-", "", $business_num_info['p_ds_biz_num']);
                        $this->low_corp_num			    = str_replace("-", "", $business_num_info['s_ds_biz_num']);
                        */
                        $pg_id = NICE_OTC_MID; // 결제 PG MID

                        $otc_pay['msgID']				= "GH";	                                // GH
                        $otc_pay['noOrder']             = $this->no_order;                      // 주문번호
                        $otc_pay['cardCompCode']        = $card_info['card_comp_code'];         // 카드사 코드
                        $otc_pay['cardID']              = $card_info['nice_cid'];               // NICE 카드 아이디
                        $otc_pay['partnerMemberID']     = $this->no_user;                       // 가맹점 회원 아이디
                        $otc_pay['productName']         = $this->nm_order;                      // 상품명
                        $otc_pay['paymentPrice']        = $this->at_price_pg;                   // 결제요청 금액
                        $otc_pay['productNum']          = ($this->product_num) ? $this->product_num : '0' ;   // 상품갯수
                        $otc_pay['mid']                 = NICE_OTC_MID;                         // 상점아이디
                        $otc_pay['licenseKey']          = NICE_OTC_KEY;                         // 상점서명인증키

                        $otc_req        = array();
                        $otc_req_value  = "";
                        if( count($otc_pay) > 0) {
                            foreach($otc_pay as $otc_k => $otc_v) {
                                if(!$otc_v) $otc_req[$otc_k] = " ";
                                else $otc_req[$otc_k] = $otc_v;
                            }
                            $otc_req_value = implode(';' , $otc_req);
                        }

                        $add_log['otc_pay'] = $otc_pay;
                        $add_log['otc_req'] = $otc_req;
                        $add_log['otc_req_value'] = $otc_req_value;

                        // SOCKET OTC결제  처리
                        $this->payment_mdl->regist_payment_complate($otc_pay ,"" , $card_info);
                        $this->cd_payment_status	= '603100'; // 결제상태
                        $this->cd_order_status		= '601100'; // 주문상태는 이전 그대로 결제요청으로 설정

                    }
                }
            }
            else // 결제 정상처리
            {
                $this->at_price_pg				= '0';
                $this->cd_payment				= '501300'; // 결제방식 캐쉬결제로 변경
                $this->cd_order_status			= '601200'; // 주문상태
                $this->cd_payment_status		= '603300'; // 결제상태
                $this->cd_pg_result				= '604050'; // PG승인구분
                $payment_result['yn_success']	= 'Y';

                // 결제정보셋팅
                // cd_pg 정보저장 추가 [2017-12-14]
                $this->payment_mdl->regist_payment_request(); //결제시도 로그저장 insert
            }

            // PG결제시 매장 수수료정보 업데이트 [추가 2018-03-16]
            $shop_commission['at_pg_commission_rate']			= ($shopinfo['at_pg_commission_rate'])?$shopinfo['at_pg_commission_rate']:0;		// PG사 수수료
            $shop_commission['cd_commission_type']				= ($shopinfo['cd_commission_type'])?$shopinfo['cd_commission_type']:0;				// 수수료방식
            $shop_commission['at_commission_amount']			= ($shopinfo['at_commission_amount'])?$shopinfo['at_commission_amount']:0;			// 수수료 대상금액
            $shop_commission['at_commission_rate']				= ($shopinfo['at_commission_rate'])?$shopinfo['at_commission_rate']:0;				// 수수료율
            $shop_commission['at_sales_commission_rate']		= ($shopinfo['at_sales_commission_rate'])?$shopinfo['at_sales_commission_rate']:0;// 영업대행 수수료율

            $this->payment_mdl->regist_shop_commission($shop_commission);

            $response['shop_commission']		= $shop_commission;

            ## 결제오류
            if($payment_result['yn_success'] == "N")
            {
                if($payment_result['cd_errcode'] == "")
                {
                    $payment_result['cd_errcode'] = "결제가 정상적으로 이루어지지 않았습니다";
                }

                // 결제실패일때 결제정보 취소
                if($this->at_cpn_disct > 0)
                {
                    // 쿠폰사용처리	취소
                    $this->coupon_mdl->refund();
                }

                // 캐시할인금액이 있는경우
                if($this->at_cash_disct	> 0	or $this->at_event_cash_disct >	0)
                {
                    // 이벤트번호
                    $no_action = make_no_action();

                    // 실캐쉬 환불
                    $this->cash_mdl->revoke_cash($this->use_cash,'123400',$use_shop_name,$no_action);

                    // 이벤트 캐쉬 환불
                    $this->cash_mdl->revoke_event_cash($this->use_event_cash,'123400',$use_shop_name,$no_action);

                    $this->cash_mdl->plus_member_cash($this->use_cash,$this->use_event_cash);
                }

                // 오류상태값 설정

                $this->at_price_pg				= '0';
                $this->cd_payment				= '501200';		// 결제방식 캐쉬결제로 변경
                $this->cd_order_status			= '601200';		// 주문상태
                //$this->cd_pickup_status			= '602400'; // 픽업상태-완료처리 [602100 변경 예정 2018-05-18]
                $this->cd_payment_status		= '603200';		// 결제상태
                $this->cd_pg_result				= '604999';		// PG승인구분
            }

            ## 결제성공 - 쿠폰처리 [종료]
            if($payment_result['yn_success'] == "Y")
            {
                ## 빌키결제 제외 . 정상결제후 쿠폰사용처리
                if($this->cd_payment != '501200')
                {
                    # 결제 1. 쿠폰사용처리
                    //self::_coupon_used('Y',$use_shop_name );
                }
                //사용한 쿠폰번호 세팅
                //$this->ds_cpn_no  = $this->list_no_event[0];
            }

            ## 결제시 사용한 쿠폰번호 세팅
            $this->ds_cpn_no  = $this->list_no_event[0];
            /*
            $add_log['payment_result']		= $payment_result;
            $add_log['ds_pg_id']			= $this->ds_pg_id;
            $add_log['ds_cpn_no']			= $this->ds_cpn_no;
            $add_log['at_price_pg']			= $this->at_price_pg;
            $add_log['cd_order_status']		= $this->cd_order_status;
            $add_log['cd_payment_status']	= $this->cd_payment_status;
            $add_log['cd_pg_result']		= $this->cd_pg_result;
            */

            #-----------------------------------------
            # 주문정보 저장
            #-----------------------------------------
            if(!$this->is_retry_order) // 최초결제인경우
            {
                // 주문정보에 매장정보를 추가한다.
                $shop['at_commission_rate']	= $this->at_commission_rate; // 수수료율
                $shop['cd_calc_status']		= '609100';  // 정산상태
                $shop['cd_send_status']		= '610100';  // 지급상태
                $shop['ds_pg_id']			= $pg_id;	 // 결제사 PG ID

                // 주문정보 저장
                $log_regist = $this->order_mdl->regist_order($shop);

                // 주문 상품정보 저장
                $this->order_mdl->regist_order_product($add_product_coupon['no_product']);

                // 무료증정쿠폰을 사용한경우 주문을 추가한다.
                if($add_product_coupon['no_event'])
                {
                    $this->order_mdl->add_coupon_product($add_product_coupon['no_product'],$add_product_coupon['no_event']);
                }
            }
            else
            {
                // 결제 재시도로 변경된 주문정보 변경
                $this->order_mdl->update_order_retry();
            }

            // 트랜잭션 종료 Commit 처리  ----------------------------------------
            if($this->master_db->trans_status() === FALSE)
            {
                $response	=  set_fail_param('E0400','A1002');
                process_close($request,$response,TRUE,$add_log);
                $this->master_db->trans_rollback();
                exit;
            }
            else
            {
                $this->master_db->trans_commit();
            }

            # 로그저장 [FNB로그 ] -----------------------------------------------------------
            if(!$this->is_retry_order) // 최초결제인경우
            {
                // 디비연결
                $this->log_db = $this->load->database('log', TRUE);
                $this->load->model('log_mdl');

                // 결제방법에 따라 로그 코드 세팅
                $this->cd_alarm_event_type	 = ($this->cd_payment_method == "504100") ? '607000' : '607001';

                // 도착알림로그저장
                $this->log_mdl->regist_gps_alarm();
            }

            #---------------------------------------------------------------------------
            # 응답문작성
            #---------------------------------------------------------------------------

            $response['ds_open_time']			= $shopinfo['ds_open_time'];
            $response['ds_close_time']			= $shopinfo['ds_close_time'];
            $response['no_order']				= $this->no_order;
            $response['no_payment']				= $this->no_payment;
            $response['cd_payment']				= (int)$this->cd_payment;
            $add_log['CHK_cd_payment_type']		= gettype($this->cd_payment);
            $response['no_device']				= $this->no_device;
            $response['ds_adver']				= $this->ds_adver;
            $response['at_bank_disct']			= $this->at_bank_disct;

            $response['payment_result']			= $payment_result['yn_success'];
            $response['payment_result']			= $this->cd_payment_status;

            $response['at_make_ready_time']		= $this->at_make_ready_time; //메뉴최소준비시간
            $response['pickup_update_time']		= $this->pickup_update_time; //업데이트 설정시간
            $response['cd_payment_method']	    = $this->cd_payment_method;

            if($this->cd_payment == '501100' )  // 일반결제일경우
            {
                // 정상처리
                $response  = set_success_param($response);
            }
            else
            {

                // [OTC개발]
                if($this->cd_payment_method == '504200' )  //  OTC결제일경우 정상 처리
                {
                    $response  = set_success_param($response);
                }
                else // 빌키결제
                {
                    if($payment_result['yn_success'] == 'Y' and $this->cd_payment_status == '603300')
                    {
                        // 정상처리
                        $response  = set_success_param($response);
                    }
                    else
                    {
                        $response  = set_fail_param('E0400',$payment_result['cd_errcode']);
                    }
                }
            }

            // 정상처리
            //$response  = set_success_param($response);

            ##  [임시] 특정매장 자동주문 수락 [2017-05-19] make::chomink ------------------------------------------
            ##  [임시]
            //			if($payment_result['yn_success'] == 'Y' and $this->cd_payment_status == '603300')
            //			{
            //				// 정상처리
            //				//$response  = set_success_param($response);
            //			}

            ##- 로그 ----------------------------------------------------------------------------------------------
            ## FnB주문 결과	로그
            $output	 = "\n#".date('Y-m-d H:i:s')."\t payment \t";
            $output	.= "[IP]".$_SERVER['SERVER_ADDR']."_".$this->no_user."_".$this->no_shop."_".$this->no_order;
            $output	.= " [dt_pickup]".$this->dt_pickup;
            $output	.= " [ds_adver]".$this->ds_adver;
            $output	.= " [no_card]".$this->no_card." [cd_pg]".$this->cd_pg;
            $output	.= " [pg_success]".$payment_result['yn_success'] ;

            $output	.= " \n\t\t\t\t\t";
            $output	.= "[at_price]".$this->at_price." [at_price_pg]".$this->at_price_pg;
            $output	.= " [at_cpn_disct]".$this->at_cpn_disct." [at_owin_cash]".$this->at_owin_cash;
            $output	.= "---------#\n";
            write_file('/private_data/owinlog/'.date('Ymd').'/fnb_confirm_log', $output ,FOPEN_READ_WRITE_CREATE);

            ## 결제결과 로그
            $payment_result_data = Array();
            if(count($payment_result) > 0 ){
                foreach ($payment_result as $key => $value)
                {
                    $payment_result_data[] = $key  . ' (' . $value . ')';
                }
                $output  = "\n\n#".date('Y-m-d H:i:s')."\t Order_payment_response #";
                $output .= "\n\t# [shop]\t".$this->no_shop."---------#";
                $output .= "\n\t# [no_order]\t".$this->no_order."---------#";
                $output .= "\n\t# [ds_res_order_no]\t".$this->ds_res_order_no."---------#";
                $output .= "\n\t# [ds_adver]\t".$this->ds_adver."---------#";
                $output .= "\n\t# [payment_result]\t".implode("_", $payment_result_data)."---------#\n\n";

                write_file('/private_data/owinlog/'.date('Ymd').'/demo_payment_log', $output ,FOPEN_READ_WRITE_CREATE);

            }

            ## - 로그 ----------------------------------------------------------------------------------------------

            // ARK 에 주문정보 전달
            // 아크서버와 통신 장애가 있을 경우 서비스 딜레이가 생기므로 별도의 프로세스로 전송한다.

            if($response['result'] == '1' )
            {
                // 빌키결재
                if($this->cd_payment_method == "504100")
                {
                    ## [로그] 주문진행상황 - FNB 진행상태 등록 [현대POC 개발]
                    $this->cd_order_process = "616101"; // 주문완료
                    $this->order_mdl->regist_order_process();

                    if($this->ds_adver)
                    {
                        //$output = "#----------Order2 11 ".date('Y-m-d H:i:s')."----".$this->no_shop."---------#\n\n\n";
                        $ark_result = shell_send_ark_server(11,$this->no_shop.$this->ds_adver.$this->no_order);//결제요청- 신규 접수[payment] 서버테스트[2017-05-29]
                    }
                    else
                    {
                        //$output = "#----------Order2 14 ".date('Y-m-d H:i:s')."----".$this->no_shop."---------#\n\n\n";

                        // ### 주문시 아크 새로고침,알림상태  추가
                        // 8ybte + 알림정보값 (0 or 1)  modify :: chomink [2017-04-05]
                        $ark_result = shell_send_ark_server(11,$this->no_shop."00000000".$this->no_order);

                        // rabbitmq 로 POS 에 도착메세지 전달
                        //require_once $_SERVER['DOCUMENT_ROOT'].'/module/rabbitmq/vendor/autoload.php';
                        //$rabbot_queue	= 'owin.'.$this->no_shop_reflash;
                        //$rabbot_data	= pack("C", '12').'y';
                        //require_once $_SERVER['DOCUMENT_ROOT'].'/module/rabbitmq/sender.php';
                    }
                    $socket_output  = 'MQ:';
                    $socket_output  .= implode(",",$ark_result);
                    write_file('/private_data/owinlog/'.date('Ymd').'/demo_payment_log', $socket_output ,FOPEN_READ_WRITE_CREATE);

                }
                // OTC결제주문의 경우 결제요청 [OTC개발]
                else
                {
                    ## [로그] 주문진행상황 - FNB 진행상태 등록 [현대POC 개발]
                    $this->cd_order_process = "616100"; // 주문요청
                    $this->order_mdl->regist_order_process();
                    
                    // SOCKET 통신 - OTC 결제요청
                    $ark_pay_result = shell_send_ark_server(42 , $otc_req_value);
                }


                ## ---------------------------------------------------------------------------------
                // 현대계정 연동 회원의 경우 실시간 위치정보 조회 - POC API
                ## ---------------------------------------------------------------------------------
                $poc_response = array();
                if( $member['yn_account_status'] == 'Y' &&  $member['nm_third_party'])
                {
                    $param['ds_login']	            = "PASS";
                    $param['no_user']               = $this->no_user;

                    $api_url            = (IS_TEST_SERVER) ? 'http://api-test.owinpay.com' :  'https://api.owinpay.com';
                    $poc_response_enc   = send_api($api_url.'/vendor/realtime_locations',$param);
                    $poc_response       = decrypt_parameter($poc_response_enc);
                }
                $response['poc_response']       = $poc_response;
                ## ---------------------------------------------------------------------------------

            }
        }

        ##6 응답
        process_close($request,$response,TRUE,$add_log);
        exit;
    }


    /**
     * 주문별 GPS 도착알림 / 점원호출 추가 [2017-06-08]
     */
    public function gps_alarm()
    {
        ##1 요청파라미터 셋팅
        $request = process_init();

        ##2 필수 파라미터 체크
        $required = array('no_order','cd_alarm_event_type');
        empty_param_chk($request,$required);

        ##3 파라미터 셋팅
        //$this->no_order				= $request['no_order'];								// 주문번호
        $this->no_user				= $request['no_user'];									// 회원번호
        $this->no_shop				= $request['no_shop'];									// 매장번호
        $this->no_device			= ($request['no_device']) ? $request['no_device'] : 0;	// 기기번호
        $this->at_lat				= $request['at_lat'];									// 위도
        $this->at_lng				= $request['at_lng'];									// 경도
        $this->at_distance			= $request['at_distance'];								// 도착지점간의 거리(미터단위 - int 형)
        $this->no_order				= $request['no_order'];									// 주문번호
        $this->yn_gps_status		= ($request['yn_gps_status'] == 'Y') ? 'Y' : 'N';		// GPS 활성화 상태
        $this->cd_alarm_event_type	= $request['cd_alarm_event_type'];

        ##4 권한인증 체크 0200
        //authorize($request);

        ##5-1 응답문셋팅::파라미터체크 0300
        $response = array();
        if(false) // 결제방식이 올바른지 체크
        {
        }
        else
        {
            // 디비연결
            $this->master_db = $this->load->database('master', TRUE);
            $this->log_db = $this->load->database('log', TRUE);
            $this->load->model('order_mdl');
            $this->load->model('log_mdl');

            // GPS 활성화 상태 로그저장
            $this->log_mdl->regist_gps_alarm();

            // 주문내역 GPS 정보 업데이트
            $this->order_mdl->update_gps_alarm();

            // 폰에서 보내는 GPS 알림인경우 아크서버로 이벤트를 보내 매장앱을 새로고침한다.
            if(in_array($this->cd_alarm_event_type,array('607100','607200')))
            {
                if($this->no_shop)
                {
                    $no_shop = $this->no_shop;
                }
                else
                {
                    $this->slave_db = $this->load->database('slave', TRUE);
                    $order_info = $this->order_mdl->get_order_info();
                    $no_shop = $order_info['no_shop'];
                }

                // ARKSERVER TEST LOG
                $output = "#----------GPS alram 14 ".date('Y-m-d H:i:s')."---[shop]".$no_shop."--[alram]".$this->cd_alarm_event_type."---------#\n\n\n";
                write_file('/private_data/owinlog/'.date('Ymd').'/alram_log', $output ,FOPEN_READ_WRITE_CREATE);
                $add_log['cmd']		= $ark_result['cmd'];
                $add_log['result']  = $ark_result['result'];

                ### 주문시 아크 새로고침,알림상태  추가
                $ark_result = shell_send_ark_server(14,$no_shop."0");
                /*
					8ybte + 알림정보값 (0 or 1)  
					주문별 GPS 도착알림-새로고침[gps_alarm] 서버테스트[2017-05-29]

					** rabbitmq 로 POS 에 도착메세지 전달
					require_once $_SERVER['DOCUMENT_ROOT'].'/module/rabbitmq/vendor/autoload.php';
					$rabbot_queue	= 'owin.'.$this->no_shop_reflash;
					$rabbot_data	= pack("C", '12').'y';
					require_once $_SERVER['DOCUMENT_ROOT'].'/module/rabbitmq/sender.php';
				*/
            }

            // 점원호출
            if(in_array($this->cd_alarm_event_type,array('607350')))
            {
                $ark_result			= shell_send_ark_server(20,$this->no_shop.$this->no_order); //점원호출 header : 20
                $ark_result_log		= $ark_result['result'];
                $response		= set_success_param($response);

                // ARKSERVER TEST LOG
                $output  = "#----------Pickup_Adminorder_worker_call 20 ".date('Y-m-d	H:i:s');
                $output .= "\t".$this->no_shop.$this->no_order."-".$ark_result_log."-------#\n\n\n";
                $file_log_path = '/private_data/owinlog/'.date('Ymd').'/alram_log';
                write_file($file_log_path, $output ,FOPEN_READ_WRITE_CREATE);
            }

            /* -- 로그 ---------------------------------------------------------------------------------------------- */
            ## FnB주문 결과	로그
            $output	 = "#".date('Y-m-d H:i:s')."\t gps_alarm";
            $output	.= "\t[".substr($_SERVER['SERVER_ADDR'],-3)."]";
            $output	.= "\t[no_user] ".$this->no_user."_".$this->no_shop."_".$this->no_order;
            $output	.= "\n\t\t\t[cd_alarm_event_type]".$this->cd_alarm_event_type;
            $output	.= "\t[no_device]".$this->no_device;
            $output	.= "\t[at_distance]".$this->at_distance;
            $output	.= "\t[yn_gps_status]".$this->yn_gps_status;
            $output	.= "---------#\n\n";
            write_file('/private_data/owinlog/'.date('Ymd').'/fnb_confirm_log', $output ,FOPEN_READ_WRITE_CREATE);
            /* -- 로그 ---------------------------------------------------------------------------------------------- */

            // 정상처리
            $response  = set_success_param($response);
        }
        ##6 응답
        process_close($request,$response,TRUE,$add_log);
    }


    /**
     *  쿠폰사용처리
     */
    private function _coupon_used($use_shop_name)
    {
        if(count($this->list_no_event) > 0 ||  count($this->rush_coupon) > 0)
        {
            // 쿠폰 사용처리
            $this->coupon_mdl->payment('Y',$use_shop_name);
        }
    }


    /**
     *  일반결제 [ FDK , LGU , KICC , KSNET , KCP ]
     *  PG사별 ds_payment_url 지정
     */
    private function _payment_501100()
    {
        // 주문상태
        $this->cd_order_status	 = '601100'; // 주문요청
        $this->cd_payment_status = '603100'; // 결제요청

        // 결제정보셋팅
        $this->payment_mdl->regist_payment_request(); //결제시도 로그저장
    }


    /**
     *  [FDK] 빌키결제
     */
    private function _fdk_payment_501200($member)
    {
        $this->load->helper('fdk');

        ## ============================================================================== ##
        # = [FDK]  회원 빌키정보 조회							 						=  #
        ## ------------------------------------------------------------------------------ ##
        if($this->cd_service_pay == '901200') // 러쉬결제
        {
            // 러쉬결제일경우 선택카드 번호가 없어 최초 등록한 빌키카드를 조회한다.
            $card_info = $this->card_mdl->get_card_info_one_pg();
        }
        else
        {
            $card_info = $this->card_mdl->get_card_info_pg();
        }


        ## ============================================================================== ##
        # = [FDK] PG 빌키결제 모듈연동 작업 						 					=  #
        ## ------------------------------------------------------------------------------ ##

        // 빌키가 없으면 종료
        if(!$card_info['ds_billkey'])
        {
            $response	=  set_fail_param('E0400','P1021');
            process_close($request,$response,TRUE,$add_log);
            exit;
        }
        else
        {
            // 서버기준 전송시간 - 취소시 사용
            $this->ds_server_reg = date('YmdHis');

            //공통
            $keyData				= FDK_KEYDATA_BILLKEY;					// 가맹점 배포 PASSKEY 입력
            $fdkSendHost			= FDK_SENDHOST;							// FDK 요청 HOST
            $fdkSendPath			= FDK_CERT_SENDPATH;					// FDK 요청 PATH
            $rtnData				 = "";									// FDK 수신 DATA

            ## ------------------------------------------------------------------------------ ##
            # = [FDK] PG 빌키결제 정보	[req_param]				 							=  #
            ## ------------------------------------------------------------------------------ ##

            $freq["MxID"]			= FDK_MXID_BILLKEY;						// [M]가맹점ID-testcorp3
            $freq["MxIssueNO"]		= $this->no_order;						// [M]주문번호
            $freq["MxIssueDate"]	= $this->ds_server_reg;					// [M]주문시간(YYYYMMDDHHMMSS)
            $freq["PayMethod"]		= "CC";									// [M]서비스종류(신용카드:CC)
            $freq["CcMode"]			= "10";									// [M]거래모드('10' 고정)
            $freq["EncodeType"]		= "U";									// [M]인코딩종류(utf-8:U, euc-kr:E)
            $freq["SpecVer"]		= "F100C000";							// [M]전문버전('F100C000' 고정)

            //승인요청시(EC132000)
            $freq["TxCode"]			= "EC132000";							// [M]거래구분(승인:EC132000, 취소:EC131400)
            $freq["Amount"]			= $this->at_price_pg;					// [M]승인시 필수  2016-07-21 이벤트지원금이 있을경우 승인금액 변경
            $freq["Currency"]		= "KRW";								// [M]화폐코드('KRW' 고정)
            $freq["Tmode"]			= "WEB";								// [M]거래방식('WEB' 고정)
            $freq["Installment"]	= "00";									// [M]할부개월('00' : 일시불, 그 외 : 할부개월 (ex. 2개월 -> '02'))

            $freq["BillType"]		= "00";									// [O]과세구분(과세:00, 면세:01, default:00)
            $freq["CcNameOnCard"]	= $member['nm_user'];					// [O]주문자명
            $freq["CcProdDesc"]		= $this->no_shop."_".$this->nm_order;	// [O]상품명 $this->no_shop
            $freq["PhoneNO"]		= $member['ds_phone'];					// [O]주문자연락처
            $freq["Email"]			= $member['id_user'];					// [O]주문자이메일주소
            $freq["BillKey"]		= $card_info['ds_billkey'];				// [M]빌링 등록시 생성된 빌링ID

            /****************************************
             * ■ Hash DATA 생성 처리
             *     MxID + MxIssueNO + Amount + keyData로 HashData 생성 처리
             ****************************************/
            $freq["FDHash"] = strtoupper(hash("sha256",$freq["MxID"].$freq["MxIssueNO"].$freq["Amount"].$keyData));

            //request DATA (Client - FDK SERVER) WEB(HTTPS) 통신 처리
            $rtnData = billkeysendHttps($fdkSendHost, $fdkSendPath, $freq);

            ## ============================================================================== ##
            # = [FDK] PG 빌키결제 결과	[res_param]				 							=  #
            ## ------------------------------------------------------------------------------ ##

            //rtnData to JSON DATA 전환 처리
            $res_param = StringToJsonProc($rtnData);

            // PG사 결제승인정보 - 정상처리
            if($res_param['ReplyCode'] == '0000' )
            {
                $this->cd_payment_status	= '603300'; // 결제상태
                $this->cd_order_status		= '601200'; // 주문상태
                $this->cd_pg_result			= '604100'; // PG승인구분
                $result['yn_success']		= 'Y';
                $result['cd_errcode']		= '';
            }
            else
            {
                $this->cd_payment_status	= '603200'; // 결제상태
                $this->cd_order_status		= '601100'; // 주문상태는 이전 그대로 결제요청으로 설정
                $this->cd_pg_result			= '604999'; //	PG승인오류
                $result['yn_success']		= 'N';
                $result['cd_errcode']		= 'P2010';
            }


            $this->at_price_pg				= $res_param['Amount'];			//결제금액
            $this->ds_res_code				= $res_param['ReplyCode'];		// PG 응답코드
            $this->ds_res_msg				= $res_param['ReplyMessage'];	// PG 응답메세지
            $result['ds_res_order_no']		= $res_param['AuthNO'];			//승인번호 PG거래번호

            $yn_credit					=	 $res_param['CheckYn'];	// PG 응답메세지

            //$response['card_info'] = 	$card_info;
            //$response['res_param'] = 	$res_param;

        }

        $user_yn_credit								= $card_info['yn_credit'];
        $result['check_param']['yn_credit']			= $yn_credit;
        $result['check_param']['user_yn_credit']	= $user_yn_credit;

        /*
			// 등록된 카드 체크카드 여부가 없을 경우 정보업데이트
			if(!$user_yn_credit)
			{
					//$add_log['no_card_card'] = $this->no_card;
					//$add_log['no_card_user'] = $this->no_user;
					//$add_log['no_card_yn_credit'] = $this->yn_credit;

				$this->card_mdl->update_card_checkyn();
			}
			$query = $this->master_db->last_query();
		*/
        // ORDER 로그 --------------------------------------------------------------------------------------------------------------
        $output   = "\n\n#".date('Y-m-d H:i:s')."\t FDK_payment";
        $output  .= "\n\t #[res_param]\t".implode("=", $res_param)."---------#";
        $output  .= "\n\t #[no_card]\t".$this->no_card."[no_user]".$this->no_user."[yn_credit]".$yn_credit."---------#";
        $output  .= "\n\t #[ds_res_code]\t".$this->ds_res_code."[ds_res_msg]".$this->ds_res_msg."---------#";
        $output  .= "\n\t #[rtnData]\t".json_encode($freq)."---------#\n";
        write_file('/private_data/owinlog/'.date('Ymd').'/demo_payment_log', $output ,FOPEN_READ_WRITE_CREATE);
        // ORDER 로그 end ---------------------------------------------------------------------------------------------------------

        ## ============================================================================== ##
        # = [FDK] 결제정보 저장 						 								=  #
        ## ------------------------------------------------------------------------------ ##
        $this->ds_res_order_no = $result['ds_res_order_no'];//PG사 거래번호
        $this->payment_mdl->regist_payment_complate($freq, $res_param, $card_info);
        ## ============================================================================== ##
        # = [FDK] 결제결과 정보 반환 				 									=  #
        ## ------------------------------------------------------------------------------ ##
        return $result;
    }


    /**
     *  [LGU]  빌키결제
     */
    private function _lg_payment_501200($member)
    {

        ## ============================================================================== ##
        # = [LGU] 회원 빌키정보 조회							 						=  #
        ## ------------------------------------------------------------------------------ ##

        if($this->cd_service_pay == '901200') // 러쉬결제
        {
            // 러쉬결제일경우 선택카드 번호가 없어 최초 등록한 빌키카드를 조회한다.
            $card_info = $this->card_mdl->get_card_info_one_pg();
        }
        else
        {
            $card_info = $this->card_mdl->get_card_info_pg();
        }


        ## ============================================================================== ##
        # = [LGU] PG 빌키결제 모듈연동 작업 						 					=  #
        ## ------------------------------------------------------------------------------ ##

        if(!$card_info['ds_billkey'])
        {
            $response	=  set_fail_param('E0400','P1021');
            process_close($request,$response,TRUE,$add_log);
            exit;
        }
        else
        {
            // 서버기준 전송시간 - 취소시 사용
            $this->ds_server_reg = date('YmdHis');

            /*
			 * [결제승인 요청 데이터]
			 */
            $this->CST_PLATFORM			= P_CST_PLATFORM; //미적용
            $this->CST_MID				= P_LGD_MID;
            $this->LGD_MID				= P_LGD_MID;

            //$this->CST_PLATFORM			= CST_PLATFORM;									// LG유플러스 결제 서비스 선택(test:테스트, service:서비스)
            //$this->CST_MID				= LGD_MID;										// 상점아이디(LG유플러스발급받은 상점아이디) - 테스트 아이디 't'제외하고	입력
            //$this->LGD_MID				= LGD_MID;										// 상점아이디(자동생성)

            $this->LGD_OID				= $this->no_order;								// 주문번호(상점정의	유니크한 주문번호를	입력하세요)
            $this->LGD_AMOUNT			= $this->at_price_pg;							// 결제금액("," 를 제외한 결제금액을	입력하세요)
            $this->LGD_PAN				= $card_info['ds_billkey'];						// 빌링키
            $this->LGD_INSTALL			= "00";											// 할부개월수
            $this->LGD_PRODUCTINFO		= $this->nm_order;								// 상품명 - iconv("EUC-KR","UTF-8",$this->nm_order)
            $this->LGD_BUYER			= $member['nm_user'];							// 고객명
            $this->LGD_BUYERID			= $member['id_user'];							// 고객 아이디
            $this->LGD_BUYEREMAIL		= '';											// 고객 이메일 - 결제후 메일발송  // $member['id_user']
            $this->LGD_BUYERPHONE		= $member["ds_phone"];							// 고객 휴대폰번호(SMS발송:선택)
            $this->VBV_ECI				= "010";										// 결제방식(KeyIn:010, Swipe:020)

            $this->LGD_EXPYEAR			= "";											// 유효기간년
            $this->LGD_EXPMON			= "";											// 유효기간월
            $this->LGD_PIN				= "";											// 비밀번호 앞2자리(옵션-주민번호를 넘기지 않으면 비밀번호도	체크 안함)
            $this->LGD_PRIVATENO		= "";											// 생년월일 6자리 (YYMMDD) or 사업자번호
            $this->LGD_ENCODING				= "UTF-8";
            $this->LGD_ENCODING_NOTEURL		= "UTF-8";
            $this->LGD_ENCODING_RETURNURL	= "UTF-8";

            $configPath						= CONFIGPATH;								// LG유플러스에서 제공한	환경파일

            ## ------------------------------------------------------------------------------ ##
            # = [LGU] PG 빌키결제 정보	[req_param]				 							=  #
            ## ------------------------------------------------------------------------------ ##
            $req_param['CST_PLATFORM']			=	$this->CST_PLATFORM;
            $req_param['CST_MID']				=	$this->CST_MID;
            $req_param['LGD_MID']				=	$this->LGD_MID;
            $req_param['LGD_OID']				=	$this->LGD_OID;
            $req_param['LGD_AMOUNT']			=	$this->LGD_AMOUNT;
            $req_param['LGD_PAN']				=	$this->LGD_PAN;
            $req_param['LGD_INSTALL']			=	$this->LGD_INSTALL;
            $req_param['LGD_PRODUCTINFO']		=	$this->LGD_PRODUCTINFO;

            $req_param['LGD_BUYER']				=	$this->LGD_BUYER;
            $req_param['LGD_BUYERID']			=	$this->LGD_BUYERID;
            $req_param['LGD_BUYERPHONE']		=	$this->LGD_BUYERPHONE;
            $req_param['LGD_BUYEREMAIL']		=	$this->LGD_BUYEREMAIL;
            $req_param['VBV_ECI']				=	$this->VBV_ECI;
            $req_param['LGD_EXPYEAR']			=	$this->LGD_EXPYEAR;
            $req_param['LGD_EXPMON']			=	$this->LGD_EXPMON;
            $req_param['LGD_PIN']				=	$this->LGD_PIN;
            $req_param['LGD_PRIVATENO']			=	$this->LGD_PRIVATENO;
            $req_param['LGD_ENCODING']			=	$this->LGD_ENCODING;
            $req_param['LGD_ENCODING_NOTEURL']	=	$this->LGD_ENCODING_NOTEURL;
            $req_param['LGD_ENCODING_RETURNURL']=	$this->LGD_ENCODING_RETURNURL;
            $req_param['DS_SERVER_REG']			=	$this->ds_server_reg;


            $this->load->library('XPayClient');
            $xpay =	$this->xpayclient->XPayClient($configPath, $this->P_CST_PLATFORM);

            $this->xpayclient->Init_TX($this->LGD_MID);

            $this->xpayclient->Set("LGD_TXNAME",				"CardAuth");
            $this->xpayclient->Set("LGD_OID",					$this->LGD_OID);
            $this->xpayclient->Set("LGD_AMOUNT",				$this->LGD_AMOUNT);
            $this->xpayclient->Set("LGD_PAN",					$this->LGD_PAN);
            $this->xpayclient->Set("LGD_INSTALL",				$this->LGD_INSTALL);
            $this->xpayclient->Set("LGD_PRODUCTINFO",			$this->LGD_PRODUCTINFO);

            $this->xpayclient->Set("LGD_BUYER",					$this->LGD_BUYER);
            $this->xpayclient->Set("LGD_BUYERID",				$this->LGD_BUYERID);
            $this->xpayclient->Set("LGD_BUYERPHONE",			$this->LGD_BUYERPHONE);
            $this->xpayclient->Set("LGD_BUYEREMAIL",			$this->LGD_BUYEREMAIL);
            $this->xpayclient->Set("LGD_BUYERIP",				$_SERVER["REMOTE_ADDR"]);
            $this->xpayclient->Set("LGD_ENCODING",				$this->LGD_ENCODING);
            $this->xpayclient->Set("LGD_ENCODING_NOTEURL",		$this->LGD_ENCODING_NOTEURL);
            $this->xpayclient->Set("LGD_ENCODING_RETURNURL",	$this->LGD_ENCODING_RETURNURL);
            $this->xpayclient->Set("VBV_ECI",					$this->VBV_ECI);
            $this->xpayclient->Set("LGD_EXPYEAR",				$this->LGD_EXPYEAR);
            $this->xpayclient->Set("LGD_EXPMON", 				$this->LGD_EXPMON);
            $this->xpayclient->Set("LGD_PIN", 					$this->LGD_PIN);
            $this->xpayclient->Set("LGD_PRIVATENO",				$this->LGD_PRIVATENO);

            ## ============================================================================== ##
            # = [LGU] PG 빌키결제 결과	[res_param]				 							=  #
            ## ------------------------------------------------------------------------------ ##

            if ($this->xpayclient->TX())
            {
                // 1)결제결과 화면처리(성공,실패 결과 처리를 하시기 바랍니다.)
                $keys =	$this->xpayclient->Response_Names();
                foreach($keys as $name)
                {
                    if($name != "LGD_BUYER"){
                        $res_param[$name] = $this->xpayclient->Response($name,	0);
                    }
                }

                // 정상처리
                if(	"0000" == $this->xpayclient->Response_Code() )
                {
                    $this->cd_payment_status	= '603300'; // 결제상태
                    $this->cd_order_status		= '601200'; // 주문상태
                    $this->cd_pg_result			= '604100'; // PG승인구분
                    $result['yn_success']		= 'Y';
                    $result['cd_errcode']		= '';

                    //최종결제요청 결과 실패
                }else{
                    //
                    $this->cd_payment_status	= '603200'; // 결제상태
                    $this->cd_order_status		= '601100'; // 주문상태는 이전 그대로 결제요청으로 설정
                    $this->cd_pg_result			= '604999';
                    $result['yn_success']		= 'N';
                    $result['cd_errcode']		= 'P2010';
                }


            }else {
                //2)API	요청실패 화면처리
                $this->cd_payment_status		= '603200'; // 결제상태
                $this->cd_order_status			= '601100'; // 주문상태는 이전 그대로 결제요청으로 설정
                $this->cd_pg_result				= '604999';
                $result['yn_success']			= 'N';
                $result['cd_errcode']			= 'P2010';
            }

            $this->at_price_pg 				= $this->xpayclient->Response("LGD_AMOUNT",0);	//결제금액
            $this->ds_res_code  			= $this->xpayclient->Response_Code();			// PG 응답코드
            $this->ds_res_msg   			= $this->xpayclient->Response_Msg();			// PG 응답메세지
            $result['ds_res_order_no']		= $this->xpayclient->Response("LGD_TID",0);		// PG거래번호
            //$this->ds_res_order_no			= $this->xpayclient->Response("LGD_TID",0);
        }

        // ORDER 로그 --------------------------------------------------------------------------------------------------------------
        $output   = "\n\n#".date('Y-m-d H:i:s')."\t LGU_payment";
        $output  .= "\n\t#[res_param]\t".implode("=", $res_param)."---------#";
        $output  .= "\n\t#[req_param]\t".json_encode($req_param)."---------#";
        $output  .= "\n\t #[ds_res_code]\t".$this->ds_res_code."[ds_res_msg]".$this->ds_res_msg."---------#";
        write_file('/private_data/owinlog/'.date('Ymd').'/demo_payment_log', $output ,FOPEN_READ_WRITE_CREATE);
        // ORDER 로그 end ---------------------------------------------------------------------------------------------------------

        ## ============================================================================== ##
        # = [LGU] 결제정보 저장 						 								=  #
        ## ------------------------------------------------------------------------------ ##
        $this->ds_res_order_no = $result['ds_res_order_no'];//PG사 거래번호
        $this->payment_mdl->regist_payment_complate($req_param,$res_param,$card_info);


        ## ============================================================================== ##
        # = [LGU] 결제결과 정보 반환 				 									=  #
        ## ------------------------------------------------------------------------------ ##

        return $result;
    }


    /**
     * [KICC]  빌키결제
     */
    private function _kicc_payment_501200($member)
    {

        ## ============================================================================== ##
        # =  [KICC] 회원 빌키정보 조회							 						=  #
        ## ------------------------------------------------------------------------------ ##
        if($this->cd_service_pay == '901200') // 러쉬결제
        {
            // 러쉬결제일경우 선택카드 번호가 없어 최초 등록한 빌키카드를 조회한다.
            $card_info = $this->card_mdl->get_card_info_one_pg();
        }
        else
        {
            $card_info = $this->card_mdl->get_card_info_pg();
        }


        ## ============================================================================== ##
        # = [KICC] PG 빌키결제 모듈연동 작업 						 					=  #
        ## ------------------------------------------------------------------------------ ##

        // 빌키가 없으면 종료
        if(!$card_info['ds_billkey'])
        {
            $response	=  set_fail_param('E0400','P1021');
            process_close($request,$response,TRUE,$add_log);
            exit;
        }
        else
        {
            // 서버기준 전송시간 - 취소시 사용
            $this->ds_server_reg = date('YmdHis');

            ## :::  배치	인증/승인 처리구분 설정
            $TRAN_CD_NOR_PAYMENT	= "00101000";							// 승인(일반)
            //$TRAN_CD_NOR_MGR		= "00201000";							// 변경(일반)

            ## ::: 결제	정보 설정 PK_DATA_PAY -	(1)공통	정보 설정  PK_DATA_COMMON
            $tr_cd				= "00101000";								// [필수]거래구분
            $currency			= "00";										// [필수]통화코드
            $client_ip			= $_SERVER['REMOTE_ADDR'];					// [필수]결제고객	IP

            ## :::   결제	정보 설정 PK_DATA_PAY -	(2)카드	정보 설정  PK_DATA_CARD
            $card_txtype	  =	"41";										// [필수][공통]처리종류 [배치인증-11/배치승인-41]***
            $req_type		  =	"0";										// [필수][공통]카드결제종류
            $wcc			  =	"@";										// [필수][공통]WCC
            $cert_type		  =	"0";										// [필수][인증]인증여부 [인증-0/ 비인증-1/pw없는 인증-2]
            $noint			  =	"00";										// [필수][승인]무이자여부
            $install_period	  = "0";										// [필수][승인]할부기간

            $card_no		  =	$card_info['ds_billkey'];					// [필수][공통]신용카드 번호
            $expire_date	  =	"";											// [필수][인증]유효기간
            $password		  =	"";											// [필수][인증]비밀번호

            $auth_value		  =	substr($member['ds_birthday'],2,6);			// [필수][인증]인증값 [생년월일]
            $user_type		  =	"";											// [필수][인증]고객구분[0:개인,1:법인]
            $card_amt		  =	$this->at_price_pg;							// [필수][승인]신용카드 결제금액

            ## ::: 주문	정보 설정 PK_DATA_ORDER
            $tot_amt		  =	$this->at_price_pg;							// [필수]결제금액
            $order_no		  = $this->no_order;							// [필수]주문번호
            $memb_user_no	  =	$this->no_user;								// [선택]가맹점 고객일련번호
            $user_id		  =	$member['id_user'];							// [선택]고객 ID
            $user_mail		  =	$member['id_user'];							// [필수]고객 E-mail
            $user_phone1	  =	$member["ds_phone"];						// [필수]가맹점 고객 연락처1
            $user_phone2	  =	"";											// [선택]가맹점 고객 연락처2
            $user_addr		  =	"";											// [선택]가맹점 고객 주소

            $user_nm		  =	$member['nm_user'];							// [필수]고객명
            $user_nm_kr		  =	iconv("utf-8","euc-kr",$member['nm_user']);	// [필수]고객명

            $product_nm		  =	$this->nm_order;							// [필수]상품명
            $product_nm_kr		  =	iconv("utf-8","euc-kr",$this->nm_order);// [필수]상품명

            $product_amt	  =	$this->at_price_pg;							// [필수]상품금액
            $product_type	  =	"0";										// [필수]상품정보구분[0:실물,1:컨텐츠]

            ## ------------------------------------------------------------------------------ ##
            # = [KICC] PG 빌키결제 정보	[req_param]				 							=  #
            ## ------------------------------------------------------------------------------ ##
            $req_param = array(
                "tr_cd				" => $tr_cd
            ,"currency			" => $currency
            ,"client_ip			" => $client_ip
            ,"card_txtype		" => $card_txtype
            ,"req_type		  	" => $req_type
            ,"wcc			  	" => $wcc
            ,"cert_type			" => $cert_type
            ,"noint			  	" => $noint
            ,"install_period	" => $install_period
            ,"card_no		  	" => $card_no
            ,"expire_date		" => $expire_date
            ,"password		  	" => $password
            ,"auth_value		" => $auth_value
            ,"user_type			" => $user_type
            ,"card_amt		 	" => $card_amt
            ,"tot_amt		 	" => $tot_amt
            ,"order_no		 	" => $order_no
            ,"memb_user_no		" => $memb_user_no
            ,"user_id		 	" => $user_id
            ,"user_nm		  	" => $user_nm
            ,"user_mail			" => $user_mail
            ,"user_phone1		" => $user_phone1
            ,"user_phone2		" => $user_phone2
            ,"user_addr			" => $user_addr
            ,"product_nm		" => $product_nm
            ,"product_amt		" => $product_amt
            ,"product_type		" => $product_type
            );


            ## ::: 전문
            $mall_id		= KICC_ID;	 //	[필수]가맹점 ID
            $tx_req_data	= "";	   // 요청전문
            $mgr_data		= "";	   // 변경정보

            ##  ::: 결제	결과
            $res_cd			  =	"";		//응답코드
            $res_msg		  =	"";		//응답메시지
            $r_order_no		  =	"";		//주문번호
            $r_msg_type		  =	"";		//거래구분
            $r_noti_type	  =	"";		//노티구분
            $r_cno			  =	"";		//PG거래번호
            $r_amount		  =	"";		//총 결제금액
            $r_auth_no		  =	"";		//승인번호
            $r_tran_date	  =	"";		//거래일시
            $r_card_no		  =	"";		//카드번호
            $r_issuer_cd	  =	"";		//발급사코드
            $r_issuer_nm	  =	"";		//발급사명
            $r_acquirer_cd	  =	"";		//매입사코드
            $r_acquirer_nm	  =	"";		//매입사명
            $r_canc_acq_date  =	"";		//매입취소일시
            $r_canc_date	  =	"";		//취소일시
            $r_refund_date	  =	"";		//환불예정일시


            ## ============================================================================== ##
            # =  [KICC] 모듈연동															=  #
            ## ------------------------------------------------------------------------------ ##

            //$this->load->library('EasyPay_Client');
            $this->load->library('EasyPay_Client');
            $easypay_client	=	$this->easypay_client->EasyPay_Client();

            $this->easypay_client->clearup_msg();
            $this->easypay_client->set_home_dir(G_HOME_DIR);
            $this->easypay_client->set_gw_url(G_GW_URL);
            $this->easypay_client->set_gw_port(G_GW_PORT);
            $this->easypay_client->set_log_dir(G_LOG_DIR);
            $this->easypay_client->set_log_level(G_LOG_LEVEL);
            $this->easypay_client->set_cert_file(G_CERT_FILE);

            if($card_txtype ==	"41")//배치 승인
            {
                /*	---------------------------------------------------------------------- */
                /*	:::	승인 요청 전문	TX_REQ_DATA										   */
                /*	---------------------------------------------------------------------- */

                // 결제정보	DATA : PK_DATA_PAY
                $pay_data =	$this->easypay_client->set_easypay_item("pay_data");

                // 결제	공통 정보 DATA : PK_DATA_COMMON
                $comm_data = $this->easypay_client->set_easypay_item("common");

                $this->easypay_client->set_easypay_deli_us(	$comm_data,		"tot_amt"	   , $tot_amt	   );
                $this->easypay_client->set_easypay_deli_us(	$comm_data,		"currency"	   , $currency	   );
                $this->easypay_client->set_easypay_deli_us(	$comm_data,		"client_ip"	   , $client_ip	   );
                $this->easypay_client->set_easypay_deli_rs(	$pay_data,		$comm_data);

                // 결제	카드 정보 DATA : PK_DATA_CARD
                $card_data = $this->easypay_client->set_easypay_item("card");

                $this->easypay_client->set_easypay_deli_us(	$card_data,		"card_txtype"	, $card_txtype		 );
                $this->easypay_client->set_easypay_deli_us(	$card_data,		"req_type"		, $req_type			 );
                $this->easypay_client->set_easypay_deli_us(	$card_data,		"card_amt"		, $card_amt			 );
                $this->easypay_client->set_easypay_deli_us(	$card_data,		"noint"			, $noint			 );
                $this->easypay_client->set_easypay_deli_us(	$card_data,		"www"			, $www				 );
                $this->easypay_client->set_easypay_deli_us(	$card_data,		"card_no"		, $card_no			 );
                $this->easypay_client->set_easypay_deli_us(	$card_data,		"install_period"	, $install_period	 );
                $this->easypay_client->set_easypay_deli_rs(	$pay_data,		$card_data );

                // 결제	주문 정보 DATA : PK_DATA_ORDER
                $order_data	= $this->easypay_client->set_easypay_item("order_data");
                $this->easypay_client->set_easypay_deli_us(	$order_data,	"order_no"		, $order_no		 );
                $this->easypay_client->set_easypay_deli_us(	$order_data,	"memb_user_no"	, $memb_user_no	 );
                $this->easypay_client->set_easypay_deli_us(	$order_data,	"user_id"		, $user_id		 );
                $this->easypay_client->set_easypay_deli_us(	$order_data,	"user_nm"		, $user_nm_kr		);
                $this->easypay_client->set_easypay_deli_us(	$order_data,	"user_mail"		, $user_mail	 );
                $this->easypay_client->set_easypay_deli_us(	$order_data,	"user_phone1"	, $user_phone1	 );
                $this->easypay_client->set_easypay_deli_us(	$order_data,	"user_phone2"	, $user_phone2	 );
                $this->easypay_client->set_easypay_deli_us(	$order_data,	"user_addr"		, $user_addr	 );
                $this->easypay_client->set_easypay_deli_us(	$order_data,	"product_type"	, $product_type	 );
                $this->easypay_client->set_easypay_deli_us(	$order_data,	"product_nm"	, $product_nm_kr	 );
                $this->easypay_client->set_easypay_deli_us(	$order_data,	"product_amt"	, $product_amt	 );

            }
            /* -------------------------------------------------------------------------- */
            /* ::: 실행																	  */
            /* -------------------------------------------------------------------------- */

            $opt		 = "";//utf-8
            $this->easypay_client->easypay_exec($mall_id, $tr_cd, $order_no,	$client_ip,	$opt);
            $res_cd	 = $this->easypay_client->_easypay_resdata["res_cd"];						 //	응답코드
            $res_msg = $this->easypay_client->_easypay_resdata["res_msg"];						 //	응답메시지

            $res_msg = iconv("euc-kr","utf-8",$res_msg);
            /* -------------------------------------------------------------------------- */
            /* ::: 결과	처리															  */
            /* -------------------------------------------------------------------------- */
            $r_cno			   = $this->easypay_client->_easypay_resdata[ "cno"			   ];	 //PG거래번호
            $r_amount		   = $this->easypay_client->_easypay_resdata[ "amount"		   ];	 //총 결제금액
            $r_order_no		   = $this->easypay_client->_easypay_resdata[ "r_order_no"	   ];	 //주문번호
            $r_noti_type	   = $this->easypay_client->_easypay_resdata[ "r_noti_type"	   ];	 //노티구분
            $r_auth_no		   = $this->easypay_client->_easypay_resdata[ "auth_no"		   ];	 //승인번호
            $r_tran_date	   = $this->easypay_client->_easypay_resdata[ "tran_date"	   ];	 //승인일시
            $r_card_no		   = $this->easypay_client->_easypay_resdata[ "card_no"		   ];	 //카드번호
            $r_issuer_cd	   = $this->easypay_client->_easypay_resdata[ "issuer_cd"	   ];	 //발급사코드
            $r_issuer_nm	   = $this->easypay_client->_easypay_resdata[ "issuer_nm"	   ];	 //발급사명
            $r_acquirer_cd	   = $this->easypay_client->_easypay_resdata[ "acquirer_cd"	   ];	 //매입사코드
            $r_acquirer_nm	   = $this->easypay_client->_easypay_resdata[ "acquirer_nm"	   ];	 //매입사명
            $r_install_period  = $this->easypay_client->_easypay_resdata[ "install_period" ];	 //할부개월
            $r_noint		   = $this->easypay_client->_easypay_resdata[ "noint"		   ];	 //무이자여부
            $r_canc_acq_date   = $this->easypay_client->_easypay_resdata[ "canc_acq_date"  ];	 //매입취소일시
            $r_canc_date	   = $this->easypay_client->_easypay_resdata[ "canc_date"	   ];	 //취소일시

            ## ============================================================================== ##
            # = [KICC] PG 빌키결제 결과	[res_param]				 							=  #
            ## ------------------------------------------------------------------------------ ##

            $res_param['res_cd']					= $res_cd;
            $res_param['res_msg']					= $res_msg;

            $res_param['r_cno']						= $r_cno;
            $res_param['r_amount']					= $r_amount;
            $res_param['r_order_no']				= $r_order_no;
            $res_param['r_noti_type']				= $r_noti_type;
            $res_param['r_auth_no']					= $r_auth_no;
            $res_param['r_tran_date']				= $r_tran_date;
            $res_param['r_card_no']					= $r_card_no;
            $res_param['r_issuer_cd']				= $r_issuer_cd;
            $res_param['r_issuer_nm']				=  iconv("EUC-KR","UTF-8",$r_issuer_nm);
            $res_param['r_acquirer_cd']				= $r_acquirer_cd;
            $res_param['r_acquirer_nm']				= iconv("EUC-KR","UTF-8",$r_acquirer_nm);
            $res_param['r_install_period']			= $r_install_period;
            $res_param['r_noint']					= $r_noint;
            $res_param['r_canc_acq_date']			= $r_canc_acq_date;
            $res_param['r_canc_date']				= $r_canc_date;

            if($res_param['res_cd'] == '0000' )
            {
                $bDBProc = "true";	   // DB처리 성공 시 "true", 실패 시 "false"

                $this->cd_payment_status	= '603300'; // 결제상태
                $this->cd_order_status		= '601200'; // 주문상태
                $this->cd_pg_result			= '604100'; // PG승인구분
                $result['yn_success'] = 'Y';
                $result['cd_errcode'] = '';

                /*
					if ( $bDBProc != "true"	)
					{
						// 승인요청이 실패 시 아래 실행
						if(	$TRAN_CD_NOR_PAYMENT ==	$tr_cd )
						{
							$this->easypay_client->clearup_msg();

							$tr_cd = $TRAN_CD_NOR_MGR;
							$mgr_data =	$this->easypay_client->set_easypay_item("mgr_data");
							$this->easypay_client->set_easypay_deli_us(	$mgr_data, "mgr_txtype"		, "40"	 );
							$this->easypay_client->set_easypay_deli_us(	$mgr_data, "org_cno"		, $r_cno	 );
							$this->easypay_client->set_easypay_deli_us(	$mgr_data, "req_ip"			, $client_ip );
							$this->easypay_client->set_easypay_deli_us(	$mgr_data, "req_id"			, "MALL_R_TRANS" );
							$this->easypay_client->set_easypay_deli_us(	$mgr_data, "mgr_msg"		, "DB 처리 실패로 망취소"  );

							$this->easypay_client->easypay_exec($g_mall_id,	$tr_cd,	$order_no, $client_ip, $opt);
							$res_cd		 = $this->easypay_client->_easypay_resdata["res_cd"	   ];	 //	응답코드
							$res_msg	 = $this->easypay_client->_easypay_resdata["res_msg"   ];	 //	응답메시지
							$r_cno		 = $this->easypay_client->_easypay_resdata["cno"	   ];	 //	PG거래번호
							$r_canc_date = $this->easypay_client->_easypay_resdata["canc_date" ];	 //	취소일시
						}
					}
				*/
            }else{
                $this->cd_payment_status	= '603200'; // 결제상태
                $this->cd_order_status		= '601100'; // 주문상태는 이전 그대로 결제요청으로 설정
                $this->cd_pg_result			= '604999';
                $result['yn_success'] = 'N';
                $result['cd_errcode'] = 'P2010';
            }

            $this->at_price_pg			= $r_amount;				//결제금액
            $this->ds_res_code			= $res_param['res_cd'];		// PG 응답코드
            $this->ds_res_msg			= $res_param['res_msg'];	// PG 응답메세지
            $result['ds_res_order_no']	= $r_cno;					//승인번호

            //$response['res_cd']		= $res_cd; //KICC
            //$response['req_data']		= $req_data; //KICC
            //$response['card_info']	= $card_info;
            //$response['resData']		= $res_param;

        }

        // ORDER 로그 --------------------------------------------------------------------------------------------------------------
        $output   = "\n\n #".date('Y-m-d H:i:s')."\t Kicc_payment";
        $output  .= "\n\t #[res_param]\t".implode("=", $res_param)."---------#";
        $output  .= "\n\t #[req_param]\t".json_encode($req_param)."---------#";
        $output  .= "\n\t #[ds_res_code]\t".$this->ds_res_code."[ds_res_msg]".$this->ds_res_msg."---------#";
        write_file('/private_data/owinlog/'.date('Ymd').'/demo_payment_log', $output ,FOPEN_READ_WRITE_CREATE);
        // ORDER 로그 end ---------------------------------------------------------------------------------------------------------

        ## ============================================================================== ##
        # = [KICC] 결제정보 저장 						 								=  #
        ## ------------------------------------------------------------------------------ ##
        $this->ds_res_order_no = $result['ds_res_order_no'];//PG사 거래번호
        $this->payment_mdl->regist_payment_complate($req_param,$res_param,$card_info);

        ## ============================================================================== ##
        # = [KICC] 결제결과 정보 반환 				 									=  #
        ## ------------------------------------------------------------------------------ ##
        return $result;

    }


    /**
     *  [KSNET]  빌키결제
     */
    private function _ksnet_payment_501200($member)
    {

        ## ============================================================================== ##
        # = [KSNET] 회원 빌키정보 조회							 						=  #
        ## ------------------------------------------------------------------------------ ##

        if($this->cd_service_pay == '901200') // 러쉬결제
        {
            // 러쉬결제일경우 선택카드 번호가 없어 최초 등록한 빌키카드를 조회한다.
            $card_info = $this->card_mdl->get_card_info_one_pg();
        }
        else
        {
            $card_info = $this->card_mdl->get_card_info_pg();
        }


        ## ============================================================================== ##
        # = [KSNET] PG 빌키결제 모듈연동 작업 						 					=  #
        ## ------------------------------------------------------------------------------ ##

        if(!$card_info['ds_billkey'])
        {
            $response	=  set_fail_param('E0400','P1021');
            process_close($request,$response,TRUE,$add_log);
            exit;
        }
        else
        {
            // 서버기준 전송시간 - 취소시 사용
            $this->ds_server_reg = date('YmdHis');

            $storeid			= KSNET_ID;											// 상점아이디
            $ordername			= iconv("UTF-8","EUC-KR",$member['nm_user']);		// 주문자명
            $ordernumber		= $this->no_order;									// 주문번호
            $amount				= $this->at_price_pg;									// 금액
            $goodname			= iconv("UTF-8","EUC-KR",$this->nm_order);			// 제품명
            $email				= $member['id_user'];								// email
            $phoneno			= $member['ds_phone'];								// phoneno
            $payKey				= $card_info['ds_billkey'];							// 빌키
            $interesttype		= "1";												// 일반/무이자구분	1:일반 2:무이자
            $installment		= "00";												// 할부 00일시불
            $currencytype		= "WON";											// 통화구분 0:원화	1: 미화

            ## ------------------------------------------------------------------------------ ##
            # = [KSNET] PG 빌키결제 정보	[req_param]				 						=  #
            ## ------------------------------------------------------------------------------ ##

            $req_param['storeid']					=	$storeid;
            $req_param['ordernumber']				=	$ordernumber;
            $req_param['amount']					=	$amount;
            $req_param['email']						=	$email;
            $req_param['phoneno']					=	$phoneno;
            $req_param['payKey']					=	$payKey;

            ## ====================================================================================== ##
            # = [KSNET] 모듈연동																	=  #
            ## -------------------------------------------------------------------------------------- ##
            $this->load->helper('ksnet');

            //Header부 Data	--------------------------------------------------
            $EncType		   = "2" ;						  //0: 암화안함, 2:	seed
            $Version		   = "0603"	;					  //전문버전
            $Type			   = "00" ;						  //구분
            $Resend			   = "0" ;						  //전송구분 : 0 : 처음,  2: 재전송
            $RequestDate	   =							  // 요청일자 :	yyyymmddhhmmss
                SetZero(strftime("%Y"),4).
                SetZero(strftime("%m"),2).
                SetZero(strftime("%d"),2).
                SetZero(strftime("%H"),2).
                SetZero(strftime("%M"),2).
                SetZero(strftime("%S"),2);

            $KeyInType		   = "K" ;							//KeyInType 여부 : K:	KeyInType
            $LineType		   = "1" ;							//lineType 1:internet
            $ApprovalCount	   = "1" ;							//복합승인갯수
            $GoodType		   = "0" ;							//제품구분 0 : 실물, 1 : 디지털
            $HeadFiller		   = ""	;							//예비

            $StoreId		   = $storeid;						//*상점아이디
            $OrderNumber	   = $ordernumber ;					//*주문번호
            $UserName		   = $ordername;					//*주문자명
            $IdNum			   = ""	;							//주민번호 or 사업자번호
            $Email			   = $email	;						//*email
            $GoodName		   = $goodname ;					//*제품명
            $PhoneNo		   = $phoneno	;					//*휴대폰번호
            //Header end -------------------------------------------------------------------

            //Data Default-------------------------------------------------
            $ApprovalType	   = "1000"	;						//승인구분
            $InterestType	   = $interesttype ;				//일반/무이자구분 1:일반 2:무이자

            $TrackII		   = "V".$payKey;					// 카드번호=유효기간
            $Installment	   = $installment;					//할부 00일시불
            $Amount			   = $amount;						//금액
            $Passwd			   = "" ;							//비밀번호 앞2자리
            $LastIdNum		   = "" ;							//주민번호 뒤7자리,사업자번호10
            $CurrencyType	   = $currencytype ;				//통화구분 0:원화 1: 미화

            $BatchUseType	   = "0" ;							//거래번호배치사용구분  0:미사용 1:사용
            $CardSendType	   = "2" ;							//카드정보전송유무
            $VisaAuthYn		   = "7" ;							//비자인증유무 0:사용안함,7:SSL,9:비자인증
            $Domain			   = ""	;							//도메인 자체가맹점(PG업체용)
            $IpAddr			   = ${"REMOTE_ADDR"};				//IP ADDRESS 자체가맹점(PG업체용)
            $BusinessNumber	   = ""	;							//사업자 번호	자체가맹점(PG업체용)
            $Filler			   = "" ;							//예비 (*카드등록을 위해 고정값으로 변경 금지)
            $AuthType		   = ""	;							//ISP	: ISP거래, MP1,	MP2	: MPI거래, SPACE : 일반거래
            $MPIPositionType   = ""	;							//K :	KSNET, R : Remote, C : 제3기관,	SPACE :	일반거래
            $MPIReUseType	   = ""	;							//Y :	재사용,	N :	재사용아님
            $EncData		   = ""	;							//MPI, ISP 데이터
            //Data Default end -------------------------------------------------------------

            //Server로 부터	응답이 없을시 자체응답
            $rApprovalType	   = "1001"	;
            $rTransactionNo	   = ""	;						  //거래번호
            $rStatus		   = "X" ;						  //상태 O : 승인, X : 거절
            $rTradeDate		   = ""	;						  //거래일자
            $rTradeTime		   = ""	;						  //거래시간
            $rIssCode		   = "00" ;						  //발급사코드
            $rAquCode		   = "00" ;						  //매입사코드
            $rAuthNo		   = "9999"	;					  //승인번호 or	거절시 오류코드
            $rMessage1		   = "승인거절"	;				  //메시지1
            $rMessage2		   = "C잠시후재시도" ;			  //메시지2
            $rCardNo		   = ""	;						  //카드번호
            $rExpDate		   = ""	;						  //유효기간
            $rInstallment	   = ""	;						  //할부
            $rAmount		   = ""	;						  //금액
            $rMerchantNo	   = ""	;						  //가맹점번호
            $rAuthSendType	   = "N" ;						  //전송구분
            $rApprovalSendType = "N" ;						  //전송구분(0 : 거절, 1 : 승인, 2:	원카드)
            $rPoint1		   = "000000000000"	;			  //Point1
            $rPoint2		   = "000000000000"	;			  //Point2
            $rPoint3		   = "000000000000"	;			  //Point3
            $rPoint4		   = "000000000000"	;			  //Point4
            $rVanTransactionNo = ""	;						  //Van 거래번호
            $rFiller		   = ""	;						  //예비
            $rAuthType		   = ""	;						  //ISP	: ISP거래, MP1,	MP2	: MPI거래, SPACE : 일반거래
            $rMPIPositionType  = ""	;						  //K :	KSNET, R : Remote, C : 제3기관,	SPACE :	일반거래
            $rMPIReUseType	   = ""	;						  //Y :	재사용,	N :	재사용아님
            $rEncData		   = ""	;						  //MPI, ISP 데이터
            //--------------------------------------------------------------------------------

            /*전문을 송신할곳을	지정(중계데몬의	IP/port) : ("210.181.28.137", 21001)*/
            KSPayApprovalCancel("127.0.0.1", 29991);

            /*요청전문조립(Header부)*/
            HeadMessage
            (
                $EncType,			// 0: 암화안함, 1:openssl, 2: seed
                $Version,			// 전문버전
                $Type,				// 구분
                $Resend,			// 전송구분 : 0 : 처음,  2: 재전송
                $RequestDate,		// 전송일
                $StoreId,			// 상점아이디
                $OrderNumber,		// 주문번호
                $UserName,			// 주문자명
                $IdNum,				// 주민번호 or 사업자번호
                $Email,				// email
                $GoodType,			// 제품구분 0 : 실물, 1 : 디지털
                $GoodName,			// 제품명
                $KeyInType,			// KeyInType 여부 : S : Swap, K: KeyInType
                $LineType,			// lineType 0 : offline, 1:internet, 2:Mobile
                $PhoneNo,			// 휴대폰번호
                $ApprovalCount,		// 복합승인갯수
                $HeadFiller			// 예비
            );

            if($CurrencyType == "WON"||$CurrencyType == "410"||$CurrencyType== "")	$CurrencyType = "0" ;
            else if($CurrencyType == "USD"||$CurrencyType == "840")	$CurrencyType = "1" ;
            else	$CurrencyType = "0" ;

            /*요청전문조립(Data부)*/
            CreditDataMessage
            (
                $ApprovalType,		// ApprovalType	   : 승인구분
                $InterestType,		// InterestType	   : 일반/무이자구분 1:일반	2:무이자
                $TrackII,			// TrackII		   : 카드번호=유효기간	or 거래번호
                $Installment,		// Installment	   : 할부  00일시불
                $Amount,			// Amount		   : 금액
                $Passwd,			// Passwd		   : 비밀번호 앞2자리
                $LastIdNum,			// IdNum		   : 주민번호  뒤7자리,	사업자번호10
                $CurrencyType,		// CurrencyType	   : 통화구분 0:원화 1:	미화
                $BatchUseType,		// BatchUseType	   : 거래번호배치사용구분  0:미사용	1:사용
                $CardSendType,		// CardSendType	   : 카드정보전송 0:미정송 1:카드번호,유효기간,할부,금액,가맹점번호	2:카드번호앞14자리 + "XXXX",유효기간,할부,금액,가맹점번호
                $VisaAuthYn,		// VisaAuthYn	   : 비자인증유무 0:사용안함,7:SSL,9:비자인증
                $Domain,			// Domain		   : 도메인	자체가맹점(PG업체용)
                $IpAddr,			// IpAddr		   : IP	ADDRESS	자체가맹점(PG업체용)
                $BusinessNumber,	// BusinessNumber  : 사업자	번호 자체가맹점(PG업체용)
                $Filler,			// Filler		   : 예비
                $AuthType,			// AuthType		   : ISP : ISP거래,	MP1, MP2 : MPI거래,	SPACE :	일반거래
                $MPIPositionType,	// MPIPositionType : K : KSNET,	R :	Remote,	C :	제3기관, SPACE : 일반거래
                $MPIReUseType,		// MPIReUseType	   : Y :  재사용, N	: 재사용아님
                $EncData			// EndData		   : MPI, ISP 데이터
            );

            ## ============================================================================== ##
            # = [KSNET] PG 빌키결제 결과	[res_param]				 						=  #
            ## ------------------------------------------------------------------------------ ##

            $arr_result	= SendSocket("1");
            //print_R($arr_result);

            //Return 부 Data	--------------------------------------------------
            $rApprovalType		= trim($arr_result["ApprovalType"]);
            $rTransactionNo		= trim($arr_result["TransactionNo"]);  			// PG거래번호
            $rStatus			= trim($arr_result["Status"]);					// 상태 O : 승인, X : 거절
            $rTradeDate			= trim($arr_result["TradeDate"]);  				// 거래일자
            $rTradeTime			= trim($arr_result["TradeTime"]);  				// 거래시간
            $rIssCode			= trim($arr_result["IssCode"]);					// 발급사코드
            $rAquCode			= trim($arr_result["AquCode"]);					// 매입사코드
            $rAuthNo			= trim($arr_result["AuthNo"]);					// 승인번호 or 거절시 오류코드

            $Message1			= trim($arr_result["Message1"]);
            $Message2			= trim($arr_result["Message2"]);
            $rMessage1			= trim(iconv("EUC-KR","UTF-8",$Message1));		// 메시지1
            $rMessage2			= trim(iconv("EUC-KR","UTF-8",$Message2));		// 메시지2
            $rCardNo			= trim($arr_result["CardNo"]);					// 카드번호
            $rExpDate			= trim($arr_result["ExpDate"]);					// 유효기간
            $rInstallment		= trim($arr_result["Installment"]);				// 할부
            $rAmount			= trim($arr_result["Amount"]);					// 금액
            $rMerchantNo		= trim($arr_result["MerchantNo"]);				// 가맹점번호
            $rAuthSendType		= trim($arr_result["AuthSendType"]);				// 전송구분= new String(this.read(2))
            $rApprovalSendType	= trim($arr_result["ApprovalSendType"]);	 		// 전송구분(0 : 거절, 1 : 승인, 2: 원카드)


            $res_param['rApprovalType']      = $rApprovalType;
            $res_param['rTransactionNo']     = $rTransactionNo;   		//	거래번호
            $res_param['rStatus']            = $rStatus;          		//	상태 O : 승인, X : 거절
            $res_param['rTradeDate']         = $rTradeDate;       		//	거래일자
            $res_param['rTradeTime']         = $rTradeTime;       		//	거래시간
            $res_param['rIssCode']           = $rIssCode;         		//	발급사코드
            $res_param['rAquCode']           = $rAquCode;         		//	매입사코드
            $res_param['rAuthNo']            = $rAuthNo;          		//	승인번호 or	거절시 오류코드
            $res_param['rMessage1']          = $rMessage1;         		//	메시지1
            $res_param['rMessage2']          = $rMessage2;         		//	메시지2
            $res_param['rAmount']            = $rAmount;            		//	금액
            $res_param['rMerchantNo']        = $rMerchantNo;				//	가맹점번호
            $res_param['rAuthSendType']      = $rAuthSendType;			//	전송구분= new String(this.read(2))
            $res_param['rApprovalSendType']  = $rApprovalSendType;  		//	전송구분(0 : 거절, 1 : 승인, 2:	원카드)
            //Return End	--------------------------------------------------

            ## ====================================================================================== ##
            # = [KSNET] Response Data																=  #
            ## -------------------------------------------------------------------------------------- ##
            $res_cd = ($arr_result["Status"] == "O")? "0000" : $rAuthNo;
            $res_msg = iconv("euc-kr","utf-8",$Message1);

            if($res_cd == "0000") ////결제발급 성공  O : 승인, X 거절
            {
                $this->cd_payment_status	= '603300'; // 결제상태
                $this->cd_order_status		= '601200'; // 주문상태
                $this->cd_pg_result			= '604100'; // PG승인구분
                $result['yn_success']		= 'Y';
                $result['cd_errcode']		= '';

            }
            else //최종결제요청 결과 실패
            {
                //
                $this->cd_payment_status	= '603200'; // 결제상태
                $this->cd_order_status		= '601100'; // 주문상태는 이전 그대로 결제요청으로 설정
                $this->cd_pg_result			= '604999';
                $result['yn_success']		= 'N';
                $result['cd_errcode']		= 'P2010';
            }

            $this->at_price_pg 				= $rAmount;
            $this->ds_res_code  			= $res_cd;		// PG 응답코드
            $this->ds_res_msg   			= $res_msg;		// PG 응답메세지
            $result['ds_res_order_no']		= $rTransactionNo;
        }

        // ORDER 로그 --------------------------------------------------------------------------------------------------------------
        $output   = "\n\n #".date('Y-m-d H:i:s')."\t Ksnet_payment";
        $output  .= "\n\t #[res_param]\t".implode("=", $res_param)."---------#";
        $output  .= "\n\t #[req_param]\t".json_encode($req_param)."---------#";
        $output  .= "\n\t #[ds_res_code]\t".$this->ds_res_code."[ds_res_msg]".$this->ds_res_msg."---------#";
        write_file('/private_data/owinlog/'.date('Ymd').'/demo_payment_log', $output ,FOPEN_READ_WRITE_CREATE);
        // ORDER 로그 end ---------------------------------------------------------------------------------------------------------

        ## ============================================================================== ##
        # = [KSNET] 결제정보 저장 						 								=  #
        ## ------------------------------------------------------------------------------ ##
        $this->ds_res_order_no = $result['ds_res_order_no'];//PG사 거래번호
        $this->payment_mdl->regist_payment_complate($req_param,$res_param,$card_info);

        ## ============================================================================== ##
        # = [KSNET] 결제결과 정보 반환 				 									=  #
        ## ------------------------------------------------------------------------------ ##
        return $result;

    }


    /**
     *  [KCP]  빌키결제
     */
    private function _kcp_payment_501200($member)
    {

        ## ============================================================================== ##
        # = [KCP] 회원 빌키정보 조회							 						=  #
        ## ------------------------------------------------------------------------------ ##

        if($this->cd_service_pay == '901200') // 러쉬결제
        {
            // 러쉬결제일경우 선택카드 번호가 없어 최초 등록한 빌키카드를 조회한다.
            $card_info = $this->card_mdl->get_card_info_one_pg();
        }
        else
        {
            $card_info = $this->card_mdl->get_card_info_pg();
        }

        ## ============================================================================== ##
        # = [KCP] PG 빌키결제 모듈연동 작업 						 					=  #
        ## ------------------------------------------------------------------------------ ##

        if(!$card_info['ds_billkey'])
        {
            $response	=  set_fail_param('E0400','P1021');
            process_close($request,$response,TRUE,$add_log);
            exit;
        }
        else
        {
            // 서버기준 전송시간 - 취소시 사용
            $this->ds_server_reg = date('YmdHis');

            ## -------------------------------------------------------------------------------------- ##
            # = [KCP] 정보세팅																		=  #
            ## -------------------------------------------------------------------------------------- ##

            $g_conf_gw_url		= G_CONF_GW_URL;
            $g_conf_home_dir	= G_CONF_HOME_DIR;
            $g_conf_log_path	= G_CONF_LOG_PATH;

            $g_conf_site_cd		= G_CONF_SITE_CD;
            $g_conf_site_key	= G_CONF_SITE_KEY;
            $g_conf_site_name	= G_CONF_SITE_NAME;
            $g_conf_log_level	= G_CONF_LOG_LEVEL;			// 변경불가
            $g_conf_gw_port		= G_CONF_GW_PORT;			// 포트번호(변경불가)

            ## 인증요청 정보
            $pay_method			= "CARD";										// 결제	방법
            $req_tx				= "pay";										// 요청종류 승인(pay)/취소,매입(mod) 요청시 사용
            $currency			= "410";										// 통화코드(고정)

            $ordr_idxx			= $this->no_order;								// 주문	번호
            $good_mny			= $this->at_price_pg;							// 결제	금액
            $good_name			= $this->nm_order;
            $buyr_name			= $member['nm_user'];

            setlocale(LC_CTYPE, 'ko_KR.euc-kr'); // 한글깨짐추가
            $good_name_kr		= iconv("utf-8","euc-kr",$this->nm_order);
            $buyr_name_kr		= iconv("utf-8","euc-kr",$member['nm_user']);
            $buyr_mail			= $member['id_user'];							// 주문자 E-Mail
            $buyr_tel1			= $member['ds_phone'];							// 주문자 전화번호
            $buyr_tel2			= $member['ds_phone'];							// 주문자 휴대폰번호

            $bt_batch_key		= $card_info['ds_billkey'];						// 배치키
            $bt_group_id		= KCP_ID;										// BA0011000348
            $quotaopt			= "00";											// 00
            $card_pay_method	= "Batch";										// Batch   // 카드 결제 방법

            /* = --------------------------------------------------------------------------	= */
            $tran_cd		= "";												// 트랜잭션 코드
            $bSucc         = "";												// DB 작업 성공 여부
            /* = --------------------------------------------------------------------------	= */
            $res_cd		   = "";												// 결과코드
            $res_msg		= "";												// 결과메시지
            $tno		   = "";												// 거래번호
            /* = --------------------------------------------------------------------------	= */
            $card_cd		 = "";												// 카드 코드
            $card_no		 = "";												// 카드 번호
            $card_name		 = "";												// 카드명
            $app_time		 = "";												// 승인시간
            $app_no			 = "";												// 승인번호
            $noinf			 = "";												// 무이자여부
            $quota			 = "";												// 할부개월
            /* = --------------------------------------------------------------------------	= */

            ## -------------------------------------------------------------------------------------- ##
            # = [KCP] PG 빌키결제 정보	[req_param]													=  #
            ## -------------------------------------------------------------------------------------- ##

            $req_param['bt_group_id']				=	$bt_group_id;		// 상점아이디
            $req_param['good_mny']					=	$good_mny;			// 금액
            $req_param['bt_batch_key']				=	$bt_batch_key;		// 배치키
            $req_param['buyr_name']					=	$buyr_name;			// 주문자명
            $req_param['buyr_mail']					=	$buyr_mail;			// 주문자ID
            $req_param['buyr_tel1']					=	$buyr_tel1;			// 주문자 전화번호

            ## ====================================================================================== ##
            # = [KCP] 모듈연동																		=  #
            ## -------------------------------------------------------------------------------------- ##

            $this->load->library('C_payplus_cli');
            $c_payplus_cli =	$this->c_payplus_cli->C_payplus_cli();
            $this->c_payplus_cli->mf_clear();

            ## =   03-1. 승인 요청

            // 업체	환경 정보
            $cust_ip = getenv( "REMOTE_ADDR" );

            if ( $req_tx ==	"pay" )
            {
                $tran_cd = "00100000";

                $common_data_set = "";
                $common_data_set .=	$this->c_payplus_cli->mf_set_data_us( "amount",	  $good_mny	   );	// 결제 요청 금액
                $common_data_set .=	$this->c_payplus_cli->mf_set_data_us( "currency", $currency	   );	// 통화코드(고정)
                $common_data_set .=	$this->c_payplus_cli->mf_set_data_us( "cust_ip",  $cust_ip );		// 결제요청자IP(옵션)
                $common_data_set .=	$this->c_payplus_cli->mf_set_data_us( "escw_mod", "N"	   );		// 에스크로여부(고정)

                $this->c_payplus_cli->mf_add_payx_data(	"common", $common_data_set );

                // 주문	정보
                $this->c_payplus_cli->mf_set_ordr_data(	"ordr_idxx",  $ordr_idxx	);
                $this->c_payplus_cli->mf_set_ordr_data(	"good_name",  $good_name_kr );
                $this->c_payplus_cli->mf_set_ordr_data(	"good_mny",	  $good_mny		);
                $this->c_payplus_cli->mf_set_ordr_data(	"buyr_name",  $buyr_name_kr );
                $this->c_payplus_cli->mf_set_ordr_data(	"buyr_tel1",  $buyr_tel1	);
                $this->c_payplus_cli->mf_set_ordr_data(	"buyr_tel2",  $buyr_tel2	);
                $this->c_payplus_cli->mf_set_ordr_data(	"buyr_mail",  $buyr_mail	);

                if ( $pay_method ==	"CARD" )
                {
                    $card_data_set;

                    $card_data_set .= $this->c_payplus_cli->mf_set_data_us( "card_mny", $good_mny ); // 결제 요청 금액

                    if ( $card_pay_method == "Batch" )
                    {
                        $card_data_set .= $this->c_payplus_cli->mf_set_data_us( "card_tx_type", "11511000"               ); // 배치 결제 요청 구분 값(고정)
                        $card_data_set .= $this->c_payplus_cli->mf_set_data_us( "quota"       , $quotaopt      ); // 할부개월
                        $card_data_set .= $this->c_payplus_cli->mf_set_data_us( "bt_group_id" , $bt_group_id   ); // 그룹ID
                        $card_data_set .= $this->c_payplus_cli->mf_set_data_us( "bt_batch_key", $bt_batch_key  ); // 배치 인증키
                    }

                    $this->c_payplus_cli->mf_add_payx_data( "card", $card_data_set );
                }
            }

            ## ====================================================================================== ##
            # = [KCP] 모듈 연동후 반환정보															=  #
            ## -------------------------------------------------------------------------------------- ##

            ## =   03-3. 실행

            if ( $tran_cd != "" )
            {
                $this->c_payplus_cli->mf_do_tx( $trace_no, $g_conf_home_dir, $g_conf_site_cd, "", $tran_cd, "",
                    $g_conf_gw_url, $g_conf_gw_port, "payplus_cli_slib", $ordr_idxx,
                    $cust_ip, "3" , 0, 0, $g_conf_key_dir, $g_conf_log_dir); // 응답 전문 처리
                //$res_cd  = $this->c_payplus_cli->m_res_cd;  // 결과 코드
                //$res_msg = $this->c_payplus_cli->m_res_msg; // 결과 메시지

            }
            else
            {
                $this->c_payplus_cli->m_res_cd  = "9562";
                $this->c_payplus_cli->m_res_msg = "연동 오류|Payplus Plugin이 설치되지 않았거나 tran_cd값이 설정되지 않았습니다.";
            }

            $res_cd  = $this->c_payplus_cli->m_res_cd;  // 결과 코드
            $res_msg = $this->c_payplus_cli->m_res_msg; // 결과 메시지
            $res_msg = 	iconv("euc-kr","utf-8",$res_msg);


            /* ============================================================================== */
            /* =   04. 승인 결과 처리                                                       = */
            /* = -------------------------------------------------------------------------- = */
            if ( $req_tx == "pay" )
            {
                if ( $res_cd == "0000" )
                {

                    $tno    = $this->c_payplus_cli->mf_get_res_data( "tno"       ); // KCP 거래 고유 번호
                    $amount = $this->c_payplus_cli->mf_get_res_data( "amount"    ); // 승인 완료 금액

                    /* = -------------------------------------------------------------------------- = */
                    /* =   04-1. 신용카드 승인 결과 처리                                            = */
                    /* = -------------------------------------------------------------------------- = */
                    if ( $pay_method == "CARD" )
                    {
                        $card_cd   = $this->c_payplus_cli->mf_get_res_data( "card_cd"   ); // 카드사 코드
                        $card_no   = $this->c_payplus_cli->mf_get_res_data( "card_no"   ); // 카드 번호
                        $card_name = $this->c_payplus_cli->mf_get_res_data( "card_name" ); // 카드 종류
                        $app_time  = $this->c_payplus_cli->mf_get_res_data( "app_time"  ); // 승인 시간
                        $app_no    = $this->c_payplus_cli->mf_get_res_data( "app_no"    ); // 승인 번호
                        $noinf     = $this->c_payplus_cli->mf_get_res_data( "noinf"     ); // 무이자 여부 ( 'Y' : 무이자 )
                        $quota     = $this->c_payplus_cli->mf_get_res_data( "quota"     ); // 할부 개월 수
                    }
                    //$res_cd = "0000";

                    $this->cd_payment_status	= '603300'; // 결제상태
                    $this->cd_order_status		= '601200'; // 주문상태
                    $this->cd_pg_result			= '604100'; // PG승인구분
                    $result['yn_success']		= 'Y';
                    $result['cd_errcode']		= '';
                }    // End of [res_cd = "0000"]
                else
                {
                    $bSucc = "false";
                    $this->cd_payment_status	= '603200'; // 결제상태
                    $this->cd_order_status		= '601100'; // 주문상태는 이전 그대로 결제요청으로 설정
                    $this->cd_pg_result			= '604999';
                    $result['yn_success']		= 'N';
                    $result['cd_errcode']		= 'P2010';
                }


                /* = -------------------------------------------------------------------------- = */
                /* =   04-3. DB 작업 실패일 경우 자동 승인 취소                                 = */
                /* = -------------------------------------------------------------------------- = */
                if ( $bSucc == "false" )
                {/*
					$this->c_payplus_cli->mf_clear();

					$tran_cd = "00200000";

					$this->c_payplus_cli->mf_set_modx_data( "tno",      $tno                         );  // KCP 원거래 거래번호
					$this->c_payplus_cli->mf_set_modx_data( "mod_type", "STSC"                       );  // 원거래 변경 요청 종류
					$this->c_payplus_cli->mf_set_modx_data( "mod_ip",   $cust_ip                     );  // 변경 요청자 IP (옵션값)
					$this->c_payplus_cli->mf_set_modx_data( "mod_desc", "결과 처리 오류 - 자동 취소" );  // 변경 사유

					$this->c_payplus_cli->mf_do_tx( $tno,  $g_conf_home_dir, $g_conf_site_cd,"",  $tran_cd, "",
													$g_conf_gw_url,  $g_conf_gw_port, "payplus_cli_slib",  $ordr_idxx,
													$cust_ip, "3" ,  0, 0,$g_conf_key_dir, $g_conf_log_dir);
					*/
                }
            }

            $this->at_price_pg 				= $amount;
            $this->ds_res_code  			= $res_cd;								// PG 응답코드
            $this->ds_res_msg   			= $res_msg;								// PG 응답메세지
            $result['ds_res_order_no']		= $tno;

            $res_param['req_tx']			= $req_tx;								// 요청 구분
            $res_param['pay_method']		= $pay_method;							// 사용한 결제 수단
            $res_param['bSucc']				= $bSucc;								// 쇼핑몰 DB - 처리 성공 여부
            $res_param['res_cd']			= $res_cd;								// 결과 코드
            $res_param['res_msg']			=  iconv("EUC-KR","UTF-8",$res_msg);	// 결과 메세지
            $res_param['ordr_idxx']			= $ordr_idxx;							// 주문번호
            $res_param['tno']				= $tno;									// PG거래번호
            $res_param['good_mny']			= $good_mny;							// 결제금액
            $res_param['good_name']			= iconv("EUC-KR","UTF-8",$good_name);	// 상품명
            $res_param['buyr_name']			= iconv("EUC-KR","UTF-8",$buyr_name);	// 주문자명
            $res_param['buyr_tel1']			= $buyr_tel1;							// 주문자 전화번호
            $res_param['buyr_tel2']			= $buyr_tel2;							// 주문자 휴대폰번호
            $res_param['buyr_mail']			= $buyr_mail;							// 주문자 E-mail

            //$res_param['mod_type']			= $mod_type;		//
            //$res_param['amount']				= $amount;			// 총 금액
            //$res_param['panc_mod_mny']		= $panc_mod_mny;	// 부분취소 요청금액
            //$res_param['panc_rem_mny']		= $panc_rem_mny;	// 부분취소 가능금액
            //$res_param['card_cd']				= $card_cd;			// 카드코드
            //$res_param['card_no']				= $card_no;			// 카드번호
            //$res_param['card_name']			= $card_name;		// 카드명
            //$res_param['app_time']			= $app_time;		// 승인시간
            //$res_param['app_no']				= $app_no;			// 승인번호
            ////$res_param['quota']				= $quota;			// 할부개월
            //$res_param['noinf']				= $noinf;			// 무이자여부 치키
        }

        // ORDER 로그 --------------------------------------------------------------------------------------------------------------
        $output   = "\n\n #".date('Y-m-d H:i:s')."\t KCP_payment";
        $output  .= "\n\t #[res_param]\t".implode("=", $res_param)."---------#";
        $output  .= "\n\t #[req_param]\t".json_encode($req_param)."---------#";
        $output  .= "\n\t #[ds_res_code]\t".$this->ds_res_code."[ds_res_msg]".$this->ds_res_msg."---------#";
        write_file('/private_data/owinlog/'.date('Ymd').'/demo_payment_log', $output ,FOPEN_READ_WRITE_CREATE);
        // ORDER 로그 end ---------------------------------------------------------------------------------------------------------

        ## ============================================================================== ##
        # = [KCP] 결제정보 저장 						 								=  #
        ## ------------------------------------------------------------------------------ ##
        $this->ds_res_order_no = $tno;//PG사 거래번호
        $this->payment_mdl->regist_payment_complate($req_param,$res_param,$card_info);
        ## ============================================================================== ##
        # = [KCP] 결제결과 정보 반환 				 									=  #
        ## ------------------------------------------------------------------------------ ##
        return $result;
    }

    /**
     *  [NICE]  빌키결제
     */
    private function _nice_payment_501200($member)
    {

        ## ============================================================================== ##
        # = [NICE] 회원 빌키정보 조회							 						=  #
        ## ------------------------------------------------------------------------------ ##

        if($this->cd_service_pay == '901200') // 러쉬결제
        {
            // 러쉬결제일경우 선택카드 번호가 없어 최초 등록한 빌키카드를 조회한다.
            $card_info = $this->card_mdl->get_card_info_one_pg();
        }
        else
        {
            $card_info = $this->card_mdl->get_card_info_pg();
        }

        ## ============================================================================== ##
        # = [NICE] PG 빌키결제 모듈연동 작업 						 					=  #
        ## ------------------------------------------------------------------------------ ##

        if(!$card_info['ds_billkey'])
        {
            $response	=  set_fail_param('E0400','P1021');
            process_close($request,$response,TRUE,$add_log);
            exit;
        }
        else
        {
            // 서버기준 전송시간 - 취소시 사용
            $this->ds_server_reg = date('YmdHis');

            ## -------------------------------------------------------------------------------------- ##
            # = [NICE] 정보세팅																		=  #
            ## -------------------------------------------------------------------------------------- ##

            $license_key   = NICE_LICENSE_KEY;     // NICE 라이센스키
            $mid           = NICE_MID;             // 가맹점아이디
            $log_path      = NICE_LOG_PATH;        // NICE 로그경로
            $pay_method    = "BILL";               // NICE 결제수단
            $is_ssl        = NICE_IS_SSL;          // 보안접속여부
            $net_cancel_pwd = NICE_CANCEL_PWD;     // 취소 패스워드
            $action_type   = "PYO";                // 서비스모드 설정 (결제: PYO, 취소: CLO)

            $char_set      = 'UTF8';

            ## 인증요청 정보
            $ordr_idxx			= $this->no_order;								// 주문	번호
            $good_mny			= $this->at_price_pg;							// 결제	금액
            $good_name			= $this->nm_order;
            $buyr_name			= $member['nm_user'];

            $good_name_kr		= iconv("utf-8","euc-kr",$this->nm_order);
            $buyr_name_kr		= iconv("utf-8","euc-kr",$member['nm_user']);
            $buyr_mail			= $member['id_user'];							// 주문자 E-Mail
            $buyr_tel1			= $member['ds_phone'];							// 주문자 전화번호

            $bt_batch_key		= $card_info['ds_billkey'];						// 배치키
            $bt_group_id		= NICE_MID;

            $card_quota         = '00';

            /* = --------------------------------------------------------------------------	= */
            $tran_cd	   = "";												// 트랜잭션 코드
            $bSucc         = "";												// DB 작업 성공 여부
            /* = --------------------------------------------------------------------------	= */
            $res_cd		   = "";												// 결과코드
            $res_msg	   = "";												// 결과메시지
            $tno		   = "";												// 거래번호
            $auth_code     = "";                                                // 승인번호
            $auth_date     = "";                                                // 승인일시
            $card_ci       = "";                                                // 카드타입 (0: 신용, 1: 체크)


            ## -------------------------------------------------------------------------------------- ##
            # = [NICE] PG 빌키결제 정보	[req_param]													=  #
            ## -------------------------------------------------------------------------------------- ##

            $req_param['bt_group_id']				=	$bt_group_id;		// 상점아이디
            $req_param['good_mny']					=	$good_mny;			// 금액
            $req_param['bt_batch_key']				=	$bt_batch_key;		// 배치키
            $req_param['buyr_name']					=	$buyr_name;			// 주문자명
            $req_param['buyr_mail']					=	$buyr_mail;			// 주문자ID
            $req_param['buyr_tel1']					=	$buyr_tel1;			// 주문자 전화번호

            ## ====================================================================================== ##
            # = [NICE] 모듈연동																		=  #
            ## -------------------------------------------------------------------------------------- ##

            $this->load->library('NicepayLite');

            ## =   03-1. 승인 요청

            // 업체	환경 정보
            $cust_ip = getenv( "REMOTE_ADDR" );

            $this->nicepaylite->m_LicenseKey  = $license_key;      // NICE 라이센스키
            $this->nicepaylite->m_MID		  = $mid;               // 가맹점아이디

            $this->nicepaylite->m_NicepayHome = $log_path;          // NICE 로그경로
            $this->nicepaylite->m_PayMethod	  = $pay_method;        // NICE 결제수단
            $this->nicepaylite->m_ssl         = $is_ssl;            // 보안접속여부
            $this->nicepaylite->m_ActionType  = $action_type;       // 서비스모드 설정 (결제: PYO, 취소: CLO)
            $this->nicepaylite->m_NetCancelPW = $net_cancel_pwd;    // 취소 비밀번호
            $this->nicepaylite->m_Amt         = $good_mny;          // 결제금액
            $this->nicepaylite->m_NetCancelAmt = $good_mny;          // 결제금액
            $this->nicepaylite->m_Moid        = $ordr_idxx;         // 주문번호

            $this->nicepaylite->m_BillKey      = $bt_batch_key;      // 빌키
            $this->nicepaylite->m_BuyerName    = $buyr_name_kr;      // 구매자이름
            $this->nicepaylite->m_GoodsName    = $good_name_kr;      // 상품이름

            $this->nicepaylite->m_CardQuota    = $card_quota;      // 할부개월

            $this->nicepaylite->m_charSet     = $char_set;

            ## ====================================================================================== ##
            # = [NICE] 모듈 연동후 반환정보															=  #
            ## -------------------------------------------------------------------------------------- ##

            ## =   03-3. 실행

            $this->nicepaylite->startAction();

            $res_cd  = $this->nicepaylite->m_ResultData['ResultCode'];  // 결과 코드
            $res_msg = $this->nicepaylite->m_ResultData['ReturnMsg'];   // 결과 메시지


            /* ============================================================================== */
            /* =   04. 승인 결과 처리                                                       = */
            /* = -------------------------------------------------------------------------- = */
            if ( $pay_method == "BILL" )
            {
                if ( $res_cd == "3001" )
                {
                    $tno       = $this->nicepaylite->m_ResultData['TID'];      // NICE 거래번호
                    $auth_code = $this->nicepaylite->m_ResultData['AuthCode']; // 승인번호
                    $auto_date = date('Y-m-d H:i:s', strtotime($this->nicepaylite->m_ResultData['AuthDate'])); // 승인일시
                    $amount    = $this->nicepaylite->m_ResultData['Amt'];      // 승인금액
                    $card_ci   = $this->nicepaylite->m_ResultData['CardCl'];   // 카드타입(0: 신용, 1: 체크)

                    $card_cd = $this->nicepaylite->m_ResultData['CardCode'];   // 카드사코드
                    $card_no = $this->nicepaylite->m_ResultData['CardNo'];     // 카드번호
                    $card_name = $this->nicepaylite->m_ResultData['CardName']; // 카드사명
                    $quota = $this->nicepaylite->m_ResultData['CardQuota'];    // 할부(00: 일시불)

                    $this->cd_payment_status	= '603300'; // 결제상태
                    $this->cd_order_status		= '601200'; // 주문상태
                    $this->cd_pg_result			= '604100'; // PG승인구분
                    $result['yn_success']		= 'Y';
                    $result['cd_errcode']		= '';
                }
                else
                {
                    $bSucc = "false";
                    $this->cd_payment_status	= '603200'; // 결제상태
                    $this->cd_order_status		= '601100'; // 주문상태는 이전 그대로 결제요청으로 설정
                    $this->cd_pg_result			= '604999';
                    $result['yn_success']		= 'N';
                    $result['cd_errcode']		= 'P2010';
                }
            }

            $this->at_price_pg 				= $amount;
            $this->ds_res_code  			= $res_cd;								// PG 응답코드
            $this->ds_res_msg   			= $res_msg;								// PG 응답메세지
            $result['ds_res_order_no']		= $tno;

            $res_param['mid']               = $mid;
            $res_param['tno']				= $tno;									// PG거래번호
            $res_param['pay_method']		= $pay_method;							// 사용한 결제 수단
            $res_param['bSucc']				= $bSucc;								// 쇼핑몰 DB - 처리 성공 여부
            $res_param['res_cd']			= $res_cd;								// 결과 코드
            $res_param['res_msg']			= $res_msg;	                            // 결과 메세지
            $res_param['ordr_idxx']			= $ordr_idxx;							// 주문번호

            $res_param['good_mny']			= $amount;							    // 승인금액
            $res_param['good_name']			= $good_name;	// 상품명
            $res_param['buyr_name']			= $buyr_name;	// 주문자명
            $res_param['buyr_tel1']			= $buyr_tel1;							// 주문자 전화번호
            $res_param['buyr_mail']			= $buyr_mail;							// 주문자 E-mail

        }

        // ORDER 로그 --------------------------------------------------------------------------------------------------------------
        $output   = "\n\n #".date('Y-m-d H:i:s')."\t NICE_payment";
        $output  .= "\n\t #[res_param]\t".implode("=", $res_param)."---------#";
        $output  .= "\n\t #[req_param]\t".json_encode($req_param)."---------#";
        write_file('/private_data/owinlog/'.date('Ymd').'/demo_payment_log', $output ,FOPEN_READ_WRITE_CREATE);
        // ORDER 로그 end ---------------------------------------------------------------------------------------------------------

        ## ============================================================================== ##
        # = [NICE] 결제정보 저장 						 								=  #
        ## ------------------------------------------------------------------------------ ##
        $this->ds_res_order_no = $tno;//PG사 거래번호
        $this->payment_mdl->regist_payment_complate($req_param,$res_param,$card_info);
        ## ============================================================================== ##
        # = [NICE] 결제결과 정보 반환 				 									=  #
        ## ------------------------------------------------------------------------------ ##
        return $result;
    }


}
