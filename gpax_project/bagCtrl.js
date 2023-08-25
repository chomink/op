'use strict';
/**
 * 회원 가방 관련 api구현 모듈 입니다.
 */
const {
  appLog,
  appErrorLog,
  appWarnLog,
} = require('../../modules/appLogUtils');
const apiCode = require('../../models/apiCode');
const { isParamValid, isCheckEmail } = require('../../modules/commonUtils');
const {
  aes256Encrypt,
  aes256EncryptJson,
  aes256Decrypt,
  aes256DecryptJson,
} = require('../../modules/aes256');
const Auth = require('../../models/Auth');
const Bag = require('../../models/Bag');
const Wallet = require('../../models/Wallet');
const Move = require('../../models/Move');
const { updateMineProc } = require('../common/commonCtrl');

const process = {
  // 회원의 가방리스트중 요청한 항목리스트 전달
  getList: async (req, res) => {
    try {
      const bodyData = aes256DecryptJson(req.body.data);
      appLog('request', '/bag/get-list', '', bodyData);
      let list = [];
      const resultDataSet = { list };

      /* -------------------------------------------
       * [S] Basic Process
       --------------------------------------------- */
      // [STEP.1] 필수 파라미터 체크 - 필수값이 없을 경우 에러코드종료
      const required_params = [
        'no_user',
        'ds_uuid',
        'cd_item_kind',
        'cd_sub_cate',
      ];
      const resultValid = isParamValid(bodyData, required_params);
      let error_code = '';
      if (resultValid.length < 1) {
        error_code = apiCode.error.notValid;
      }
      //[STEP.2] Login Check
      const auth = new Auth(req, bodyData);
      const resultLogin = await auth.verifyLogin();
      if (resultLogin.resultCode !== apiCode.error.success) {
        error_code = apiCode.error.notLogin;
      }
      // 에러코드시 종료
      if (error_code) {
        const responseData = {
          resultData: resultDataSet,
          resultCode: error_code,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      /* -------------------------------------------
       * [E] Basic Process
       --------------------------------------------- */
      const bag = new Bag(req, bodyData);

      // [STEP.3-1] NFT레벨업 완료시간 초과건 레벨업완료처리
      // nft 고유번호 배열값으로 세팅
      const levelupList = await bag.getLevelUpListUser(bodyData.no_user);

      //레벨업 완료처리건이 있을경우
      if (levelupList.nftList.length > 0) {
        //[STEP.3-2] NFT레벨업 요청내역 완료처리
        const resultComplete = await bag.levelUpComplete(
          bodyData.no_user,
          levelupList
        );
        /* ------------------------------------------------
         * [S] 채굴정보 업데이트 : 공통사용[Condition Proc]
         * ------------------------------------------------ */
        const req_param = {
          no_user: bodyData.no_user,
        };
        const conditionProcResult = await updateMineProc(req_param);
        if (conditionProcResult.resultCode !== apiCode.error.success) {
          console.log('updateMineProc : ', conditionProcResult);
          appWarnLog(
            'error',
            '/src/bagCtrl/getList/updateMineProc',
            bodyData.no_user,
            conditionProcResult.resultCode
          );
        }
        /* ------------------------------------------------
         * [E] 채굴정보 업데이트
         * ------------------------------------------------ */
      }

      // [STEP.2] 요청한 카테고리정보로 회원의 아이템리스트 조회
      // 마켓, bag카테고리리스트
      if (bodyData.cd_item_kind == apiCode.service.cd_item_kind.rune) {
        // rune list -- (추가개발 필요)
        list = await bag.callBagRuneList();
      } else if (bodyData.cd_item_kind == apiCode.service.cd_item_kind.other) {
        // other list -- (추가개발 필요-other만가능)
        // console.log('other list');
        list = await bag.callBagOtherList();
      } else {
        // [Main] nft list
        list = await bag.callBagNftList();
      }

      //[STEP.4] 결과값 세팅
      const responseData = {
        resultData: list,
        resultCode: apiCode.error.success,
      };
      const encryptData = aes256EncryptJson(responseData);
      appLog('response', '/src/bagCtrl/get-list', '', responseData);
      return res.json({ data: encryptData });
    } catch (error) {
      appErrorLog('error', '/src/bagCtrl/get-list', '', error);
    }
  },

  // nft 레벨업 요청시 노출정보 요청
  setLevelUpInfo: async (req, res) => {
    try {
      const bodyData = aes256DecryptJson(req.body.data);
      appLog('request', '/bag/set-levelup-info', '', bodyData);
      /* -------------------------------------------
       * [S] Basic Process
       --------------------------------------------- */
      // [STEP.1] 필수 파라미터 체크 - 필수값이 없을 경우 에러코드종료
      const required_params = ['no_user', 'ds_uuid', 'no_nft'];
      const resultValid = isParamValid(bodyData, required_params);
      if (resultValid.length < 1) {
        const responseData = {
          resultCode: apiCode.error.notValid,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }
      //[STEP.2] Login Check
      const auth = new Auth(req, bodyData);
      const resultLogin = await auth.verifyLogin();
      if (resultLogin.resultCode !== apiCode.error.success) {
        const responseData = {
          resultCode: apiCode.error.notLogin,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      /* -------------------------------------------
       * [E] Basic Process
       --------------------------------------------- */
      const bag = new Bag(req, bodyData);

      // [STEP.2] NFT레벨업 시도에 필요한 정보 조회
      // NFT 정보및 레벨업성공시 추가될 스텟수치 (ADD Stat)
      const resultNft = await bag.callLevelUpNftAdd();
      console.log('resultNft : ', resultNft);
      if (resultNft.length < 1) {
        const responseData = { resultCode: apiCode.error.notNft };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }
      const at_level_up = resultNft[0].at_level_up;
      const at_hp = resultNft[0].at_hp;
      const cd_quality_type = resultNft[0].cd_quality_type;

      // 레벨업 가능한 nft 확인
      let errorCode = '';

      // 에그포켓일 경우 레벨업요청 실패후 종료
      if (resultNft[0].cd_nft_kind === apiCode.service.cd_nft_kind.pocket) {
        errorCode = apiCode.error.failLevelUpKind;
      }
      console.log('cd_nft_status : ', resultNft[0].cd_nft_status);
      // nft가 정상사용상태가 아닌경우 레벨업실패 메세지전달후 종료
      if (resultNft[0].cd_nft_status !== apiCode.service.cd_nft_status.stay) {
        switch (resultNft[0].cd_nft_status) {
          case apiCode.service.cd_nft_status.sale:
            errorCode = '0115'; // apiCode.error.failLevelUpSale;
            break;
          case apiCode.service.cd_nft_status.mint:
            errorCode = '0116'; //apiCode.error.failLevelUpMint;
            break;
          case apiCode.service.cd_nft_status.level:
            errorCode = '0119';
            // alreadyLevelUp: '0119'failLevelUpLevel
            break;
          default: //apiCode.error.failLevelUp;
            errorCode = '0114';
            break;
        }
      }

      if (errorCode) {
        // 에러코드가 있는상태의 경우 종료
        const responseData = { resultCode: errorCode };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      // 레벨업시 사용될 정보 (비용, 시간)
      const result = await bag.callLevelUpInfo(at_level_up, cd_quality_type);
      if (result.length < 1) {
        const responseData = { resultCode: apiCode.error.notNft };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }
      result.at_hp = at_hp; // nft 현재 레벨정보 추가
      console.log('at_level_up : ', at_level_up);
      console.log('resultNft : ', resultNft);
      console.log('result : ', result);

      let at_mxt_cost = result.at_mxt_cost;
      let at_pxbt_cost = result.at_pxbt_cost;
      let at_fast_cost = result.at_fast_cost;
      let at_take_time = result.at_take_time;
      let at_level_up_new = result.at_level_up;
      let at_hp_new = result.at_hp;

      //[STEP.4] 결과값 세팅
      const responseData = {
        //result,
        at_mxt_cost: at_mxt_cost,
        at_pxbt_cost: at_pxbt_cost,
        at_fast_cost: at_fast_cost,
        at_take_time: at_take_time,
        at_level_up: at_level_up_new,
        at_hp: at_hp_new,
        resultCode: apiCode.error.success,
      };
      const encryptData = aes256EncryptJson(responseData);
      appLog('response', '/src/bagCtrl/set-levelup-info', '', responseData);
      return res.json({ data: encryptData });
    } catch (error) {
      appErrorLog('error', '/src/bagCtrl/set-levelup-info', '', error);
    }
  },

  // nft 레벨업요청 처리
  levelUp: async (req, res) => {
    try {
      const bodyData = aes256DecryptJson(req.body.data);
      appLog('request', '/bag/levelup', '', bodyData);
      /* -------------------------------------------
       * [S] Basic Process
       --------------------------------------------- */
      // [STEP.1] 필수 파라미터 체크 - 필수값이 없을 경우 에러코드종료
      const required_params = [
        'no_user',
        'ds_uuid',
        'no_nft',
        'at_level_up',
        'ds_pwd',
      ];
      const resultValid = isParamValid(bodyData, required_params);
      if (resultValid.length < 1) {
        const responseData = {
          resultCode: apiCode.error.notValid,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }
      //[STEP.2] Login Check
      const auth = new Auth(req, bodyData);
      const resultLogin = await auth.verifyLogin();
      if (resultLogin.resultCode !== apiCode.error.success) {
        const responseData = {
          resultCode: apiCode.error.notLogin,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      /* -------------------------------------------
       * [E] Basic Process
       --------------------------------------------- */
      const wallet = new Wallet(req, bodyData);
      const bag = new Bag(req, bodyData);

      // [STEP.1] 지갑 비밀번호 검증
      const resultPwd = await wallet.callWalletPwd();
      // 비번확인 실패
      if (resultPwd < 1) {
        const responseData = {
          resultCode: apiCode.error.failWalletPwd,
        };
        const encryptData = aes256EncryptJson(responseData);
        appErrorLog(
          'error',
          '/src/bagCtrl/levelup',
          bodyData.no_user,
          apiCode.error.failWalletPwd
        );
        return res.json({ data: encryptData });
      }

      // [STEP.2] NFT레벨업 시도에 필요한 정보 조회
      // NFT 정보및 레벨업성공시 추가될 스텟수치 (ADD Stat)
      const resultNft = await bag.callLevelUpNftAdd();

      if (resultNft.length < 1) {
        const responseData = { resultCode: apiCode.error.notNft };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      let errorCode = '';
      // 에그포켓일 경우 레벨업요청 실패후 종료
      if (resultNft[0].cd_nft_kind === apiCode.service.cd_nft_kind.pocket) {
        errorCode = apiCode.error.failLevelUpKind;
      }

      console.log('cd_nft_status : ', resultNft[0].cd_nft_status);
      // nft가 정상사용상태가 아닌경우 레벨업실패 메세지전달후 종료
      if (resultNft[0].cd_nft_status !== apiCode.service.cd_nft_status.stay) {
        switch (resultNft[0].cd_nft_status) {
          case apiCode.service.cd_nft_status.sale:
            errorCode = '0115'; // apiCode.error.failLevelUpSale;
            break;
          case apiCode.service.cd_nft_status.mint:
            errorCode = '0116'; //apiCode.error.failLevelUpMint;
            break;
          case apiCode.service.cd_nft_status.level:
            errorCode = '0119';
            // alreadyLevelUp: '0119'failLevelUpLevel
            break;
          default: //apiCode.error.failLevelUp;
            errorCode = '0114';
            break;
        }
      }

      console.log('errorCode', errorCode);
      console.log('resultNft', resultNft);

      // 에러코드가 있는상태의 경우 종료
      if (errorCode) {
        const responseData = { resultCode: errorCode };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }
      const at_level_up = resultNft[0].at_level_up;
      const cd_quality_type = resultNft[0].cd_quality_type;
      console.log('CHECK levelup 1');

      // [STEP.3] 레벨업처리내역 등록
      // 레벨업시 사용될 정보 (비용, 시간)
      const result = await bag.callLevelUpInfo(at_level_up, cd_quality_type);
      console.log('CHECK levelup 2', result);
      let at_mxt_cost = Number(result.at_mxt_cost);
      let at_pxbt_cost = Number(result.at_pxbt_cost);
      let at_fast_cost = Number(result.at_fast_cost);
      let at_take_time = result.at_take_time;
      let at_level_up_new = result.at_level_up;

      if (result.length < 1) {
        const responseData = { resultCode: apiCode.error.notNft };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }
      console.log('resultNft : ', resultNft);
      console.log('result : ', result);
      // 전달받은값 레벨업 비용 확인
      if (
        bodyData.at_level_up != at_level_up ||
        bodyData.at_take_time != result.at_take_time ||
        bodyData.at_mxt_cost != result.at_mxt_cost ||
        bodyData.at_pxbt_cost != result.at_pxbt_cost
      ) {
        const responseData = { resultCode: apiCode.error.failParam };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      // 회원 게임지갑에 보유하고있는 코인정보 조회
      const resultCoin = await wallet.callGameCoinInfo();
      let user_mxt = Number(resultCoin[0].at_mxt);
      let user_pxbt = Number(resultCoin[0].at_pxbt);
      console.log('resultCoin : ', resultCoin);

      // 코인이 부족할경우 - 에러종료
      if (at_mxt_cost > user_mxt) {
        const responseData = { resultCode: apiCode.error.failShortMxt };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      if (at_pxbt_cost > user_pxbt) {
        const responseData = { resultCode: apiCode.error.failShortPxbt };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      // const responseData_t = { resultCode: '12345' };
      // const encryptData_t = aes256EncryptJson(responseData_t);
      // return res.json({ data: encryptData_t });

      // [STEP.3-1] 레벨업 등록 DB처리  - 레벨업 요청처리에 대한 정보등록
      let data = {};
      data.at_fast_cost = at_fast_cost;
      data.at_mxt_new = (user_mxt * 10 - at_mxt_cost * 10) / 10;
      data.at_pxbt_new = (user_pxbt * 10 - at_pxbt_cost * 10) / 10;
      data.at_mxt_cost = at_mxt_cost;
      data.at_pxbt_cost = at_pxbt_cost;
      data.no_game_wt = resultCoin[0].no_game_wt;

      // 레벨업처리 - 내용등록
      const resultReg = await bag.insertLevelUpProc(data);
      console.log('resultReg : ', resultReg);
      if (resultReg !== apiCode.error.success) {
        const responseData = { resultCode: apiCode.error.failLevelProc };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      //[STEP.4] 결과값 세팅
      const responseData = {
        at_mxt_cost: at_mxt_cost,
        at_pxbt_cost: at_pxbt_cost,
        at_fast_cost: at_fast_cost,
        at_take_time: at_take_time,
        at_level_up: at_level_up_new,
        resultCode: apiCode.error.success,
      };
      const encryptData = aes256EncryptJson(responseData);
      appLog('response', '/src/bagCtrl/levelup', '', responseData);
      return res.json({ data: encryptData });
    } catch (error) {
      appErrorLog('error', '/src/bagCtrl/levelup', '', error);
    }
  },

  // nft 레벨업 진행시 노출정보 요청
  levelUpInfo: async (req, res) => {
    try {
      const bodyData = aes256DecryptJson(req.body.data);
      appLog('request', '/bag/levelup-Info', '', bodyData);
      /* -------------------------------------------
       * [S] Basic Process
       --------------------------------------------- */
      // [STEP.1] 필수 파라미터 체크 - 필수값이 없을 경우 에러코드종료
      const required_params = ['no_user', 'ds_uuid', 'no_nft'];
      const resultValid = isParamValid(bodyData, required_params);
      if (resultValid.length < 1) {
        const responseData = {
          resultCode: apiCode.error.notValid,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }
      //[STEP.2] Login Check
      const auth = new Auth(req, bodyData);
      const resultLogin = await auth.verifyLogin();
      if (resultLogin.resultCode !== apiCode.error.success) {
        const responseData = {
          resultCode: apiCode.error.notLogin,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      /* -------------------------------------------
       * [E] Basic Process
       --------------------------------------------- */
      const bag = new Bag(req, bodyData);

      // [STEP.2] NFT레벨업 요청한 정보전달
      const result = await bag.callLevelUpProcInfo();
      console.log('result : ', result);
      // 처리중인 레벨업이 없을 경우 - 레벨업없음 오류전달
      if (result.length < 1) {
        const responseData = {
          resultCode: apiCode.error.failLevelUpStatus,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      // 남은시간 노출
      console.log(result[0].dt_now, '[check 01]', result[0].dt_levelup_end);

      let start = new Date(result[0].dt_now);
      let end = new Date(result[0].dt_levelup_end);
      let diff = end.getTime() - start.getTime();
      let diffSecs = parseInt((end.getTime() - start.getTime()) / 1000);
      let diffHours = parseInt((end.getTime() - start.getTime()) / (1000 * 60));
      let diff_h = Math.floor(
        (diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)
      );
      let diff_m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
      let diff_s = Math.floor((diff % (1000 * 60)) / 1000);

      // 전달데이터 정리
      const resultData = {};
      resultData.at_level_up = result[0].at_level_up;
      resultData.at_fast_cost = result[0].at_fast_cost;
      resultData.at_take_time = result[0].at_take_time;
      resultData.diffSecs = diffSecs;
      resultData.cd_levelup_status = result[0].cd_levelup_status;
      //resultData.dt_end = result[0].dt_levelup_end; //종료시간
      //resultData.dt_now = result[0].dt_now; // 현재시간

      //[STEP.4] 결과값 세팅
      const responseData = {
        resultData,
        resultCode: apiCode.error.success,
      };
      const encryptData = aes256EncryptJson(responseData);
      appLog('response', '/src/bagCtrl/levelup-info', '', responseData);
      return res.json({ data: encryptData });
    } catch (error) {
      appErrorLog('error', '/src/bagCtrl/levelup-info', '', error);
    }
  },

  // nft 레벨업 부스터사용 - 레벨업완료처리
  levelUpBoost: async (req, res) => {
    const bodyData = aes256DecryptJson(req.body.data);
    try {
      appLog('request', '/bag/levelup-boost', '', bodyData);
      /* -------------------------------------------
       * [S] Basic Process
       --------------------------------------------- */
      // [STEP.1] 필수 파라미터 체크 - 필수값이 없을 경우 에러코드종료
      const required_params = [
        'no_user',
        'ds_uuid',
        'no_nft',
        'at_fast_cost',
        'ds_pwd',
      ];
      const resultValid = isParamValid(bodyData, required_params);
      if (resultValid.length < 1) {
        const responseData = {
          resultCode: apiCode.error.notValid,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }
      //[STEP.2] Login Check
      const auth = new Auth(req, bodyData);
      const resultLogin = await auth.verifyLogin();
      if (resultLogin.resultCode !== apiCode.error.success) {
        const responseData = {
          resultCode: apiCode.error.notLogin,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      /* -------------------------------------------
       * [E] Basic Process
       --------------------------------------------- */
      const bag = new Bag(req, bodyData);
      const wallet = new Wallet(req, bodyData);

      // [STEP.1] 지갑 비밀번호 검증
      const resultPwd = await wallet.callWalletPwd();
      // 비번확인 실패
      if (resultPwd < 1) {
        const responseData = {
          resultCode: apiCode.error.failWalletPwd,
        };
        const encryptData = aes256EncryptJson(responseData);
        appErrorLog(
          'error',
          '/src/bagCtrl/levelup-boost',
          bodyData.no_user,
          apiCode.error.failWalletPwd
        );
        return res.json({ data: encryptData });
      }

      // [STEP.2] NFT레벨업 요청한 정보전달
      const result = await bag.callLevelUpProcInfo();
      console.log('result : ', result);

      if (result.length < 1) {
        const responseData = {
          resultCode: apiCode.error.failLevelUpStatus,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      // [STEP.2-1] 회원보유 mxt 내역 확인
      const resultMxt = await wallet.callGameCoinInfo();

      // [STEP.3] 에러코드 세팅
      let errorCode = '';
      // 전달받은 부스터수량이 동일한지 확인
      if (bodyData.at_fast_cost !== result[0].at_fast_cost) {
        // 부스터 비용이 다를경우 처리불가 - 에러코드
        errorCode = apiCode.error.differBoostCost;
      }

      // 레벨업 진행중인지 상태확인
      let success_status = apiCode.service.cd_levelup_status.prog;

      if (result[0].cd_levelup_status !== success_status) {
        // 레벨업 진행상태가 아닐경우 처리 불가 - 에러코드
        errorCode = apiCode.error.failLevelUpStatus; /// [TEST] 테스트를위해 중지처리
      }
      if (resultMxt[0].at_mxt < bodyData.at_fast_cost) {
        // 보유 MXT 수량이 부족할경우 처리불가  - 에러코드
        errorCode = apiCode.error.failHoldMxt; /// [TEST] 테스트를위해 중지처리
      }
      // 에러코드가 있을경우 처리불가 - 종료
      if (errorCode) {
        const responseData = {
          resultCode: errorCode,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      // //[STEP.3-1] 부스터처리 mxt 사용처리 / 내역 저장
      let at_level_up = result[0].at_level_up;
      const resultUpt = await wallet.insertGameCoinUse(resultMxt, at_level_up);
      console.log('resultUpt : ', resultUpt);

      // [STEP.4] 레벨업 즉시 완료처리
      // 배치참고//levelup - complete;
      // [STEP.4-1] 5레벨업 일경우 부화처리 > PET 랜덤지급
      const resultComp = await bag.updateLevelUpBoostComplete();
      console.log('resultComp : ', resultComp);

      /* ------------------------------------------------
       * [S] 채굴정보 업데이트 : 공통사용[Condition Proc]
       * ------------------------------------------------ */
      const req_param = {
        no_user: bodyData.no_user,
      };
      const conditionProcResult = await updateMineProc(req_param);
      if (conditionProcResult.resultCode !== apiCode.error.success) {
        console.log('updateMineProc : ', conditionProcResult);
        appWarnLog(
          'error',
          '/src/bagCtrl/levelUpBoost/updateMineProc',
          bodyData.no_user,
          conditionProcResult.resultCode
        );
      }
      /* ------------------------------------------------
       * [E] 채굴정보 업데이트
       * ------------------------------------------------ */

      //[STEP.5] 결과값 세팅
      const responseData = {
        resultCode: apiCode.error.success,
      };
      const encryptData = aes256EncryptJson(responseData);
      appLog(
        'response',
        '/src/bagCtrl/levelup-boost',
        bodyData.no_user,
        responseData
      );
      return res.json({ data: encryptData });
    } catch (error) {
      appErrorLog(
        'error',
        '/src/bagCtrl/levelup-boost',
        bodyData.no_user,
        error
      );
    }
  },

  // EGG/PET의경우 - 리스트호출시 레벨업초과 건수는 레벨업완료처리 (회원기준)
  hatchComplete: async (req, res) => {
    const bodyData = aes256DecryptJson(req.body.data);
    try {
      appLog('request', '/bag/hatch-complete', '', bodyData);
      /* -------------------------------------------
       * [S] Basic Process
       --------------------------------------------- */
      // [STEP.1] 필수 파라미터 체크 - 필수값이 없을 경우 에러코드종료
      const required_params = ['no_user', 'ds_uuid', 'no_nft', 'cd_pet_kind'];
      const resultValid = isParamValid(bodyData, required_params);
      if (resultValid.length < 1) {
        const responseData = {
          resultCode: apiCode.error.notValid,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }
      //[STEP.2] Login Check
      const auth = new Auth(req, bodyData);
      const resultLogin = await auth.verifyLogin();
      if (resultLogin.resultCode !== apiCode.error.success) {
        const responseData = {
          resultCode: apiCode.error.notLogin,
        };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }

      /* -------------------------------------------
       * [E] Basic Process
       --------------------------------------------- */
      const bag = new Bag(req, bodyData);

      // [STEP.1] 부화 요청한 NFT의 존재여부와 상태확인
      const result = await bag.callLevelUpHatchInfo();
      console.log('result : ', result);

      // 내역 없을 경우 종료
      if (result.length < 1) {
        const responseData = { resultCode: apiCode.error.notNft };
        const encryptData = aes256EncryptJson(responseData);
        return res.json({ data: encryptData });
      }
      // [STEP.2] NFT레벨업 요청내역 완료처리
      const resultComplete = await bag.levelUpHatchComplete();
      console.log('resultComplete : ', resultComplete);
      console.log('resultComplete  resultCode : ', resultComplete.resultCode);
      /* ------------------------------------------------
       * [S] 채굴정보 업데이트 : 공통사용[Condition Proc]
       * ------------------------------------------------ */
      const req_param = {
        no_user: bodyData.no_user,
      };
      const conditionProcResult = await updateMineProc(req_param);
      if (conditionProcResult.resultCode !== apiCode.error.success) {
        console.log('updateMineProc : ', conditionProcResult);
        appWarnLog(
          'error',
          '/src/bagCtrl/hatchComplete/updateMineProc',
          bodyData.no_user,
          conditionProcResult.resultCode
        );
      }
      /* ------------------------------------------------
       * [E] 채굴정보 업데이트
       * ------------------------------------------------ */

      // //[STEP.4] 결과값 세팅
      const responseData = {
        resultCode: resultComplete.resultCode,
      };
      const encryptData = aes256EncryptJson(responseData);
      appLog(
        'response',
        '/src/bagCtrl/hatch-complete',
        bodyData.no_user,
        responseData
      );
      return res.json({ data: encryptData });
    } catch (error) {
      appErrorLog(
        'error',
        '/src/bagCtrl/hatch-complete',
        bodyData.no_user,
        error
      );
    }
  },
};

module.exports = process;
