'use strict';
/**
 * AES256 암복호화 모듈 입니다.
 */

const crypto = require('crypto');

const aesKey = process.env.AES256_KEY;
const iv = Buffer.from([
  0x12, 0x0e, 0x2e, 0x4b, 0x4f, 0x51, 0x32, 0x09, 0x6b, 0x71, 0x3a, 0x79, 0x17,
  0x3a, 0x1a, 0x6d,
]);

/**
 * 평문을 암호화 합니다.
 */
const aes256Encrypt = plainText => {
  let cipher = crypto.createCipheriv('aes-256-cbc', aesKey, iv);
  let result = cipher.update(plainText, 'utf8', 'base64');
  result += cipher.final('base64');
  return encodeURIComponent(result);
};

/**
 * json data를 string으로 만들어 암호화 합니다.
 */
const aes256EncryptJson = plainText => {
  const jsonData = JSON.stringify(plainText);
  return aes256Encrypt(jsonData);
};

/**
 * 암호화 문장을 평문으로 복호화 합니다.
 */
const aes256Decrypt = cryptogram => {
  const decodeData = decodeURIComponent(cryptogram);
  const decipher = crypto.createDecipheriv('aes-256-cbc', aesKey, iv);
  let result = decipher.update(decodeData, 'base64', 'utf8');
  result += decipher.final('utf8');
  return result;
};

/**
 * 복호화된 string을 json data로 만들어 리턴 합니다.
 */
const aes256DecryptJson = cryptogram => {
  const decryptData = aes256Decrypt(cryptogram);
  return JSON.parse(decryptData);
};

module.exports = {
  aes256EncryptJson,
  aes256DecryptJson,
};
