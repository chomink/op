'use strict';
/**
 * Bag api 모듈 입니다.
 */

const express = require('express');
const router = express.Router();
const ctrl = require('./bagCtrl');

// 회원가방에 보유한 아이템리스트 정보 전달
router.post('/get-list', ctrl.getList);

// nft 레벨업 요청시 노출정보 요청
router.post('/set-levelup-info', ctrl.setLevelUpInfo);

// nft 레벨업요청 처리
router.post('/levelup', ctrl.levelUp);

// nft 레벨업요청 처리진행 정보 전달
router.post('/levelup-info', ctrl.levelUpInfo);

// nft 레벨업 부스터사용 - 바로처리
router.post('/levelup-boost', ctrl.levelUpBoost);

// 5레벨업 수동완료 처리- 부화
router.post('/hatch-complete', ctrl.hatchComplete);

module.exports = router;
