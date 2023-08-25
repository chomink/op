const { keystore } = require('eth-lightwallet');
const { ethers } = require('ethers');
const contractAbi = require('./abi/pxbtContract.json');
const { appLog, appErrorLog } = require('../../modules/appLogUtils');
/**
 * 니모닉 요청
 */
const newMnemonic = () => {
  const mnemonic = keystore.generateRandomSeed();

  return {
    resultCode: 'success',
    mnemonic,
  };
};

/**
 * 요청한 니모닉과 비밀번호로 지갑 생성 요청
 *
 * password: (필수) 시리얼화 시 볼트를 암호화하기 위해 사용되는 문자열.
 * mnemonic: 니모직 12자리 문자
 * seedPhrase: (필수) 모든 계정을 생성하기 위해 사용되는 12단어 니모닉.
 * salt: (옵션)사용자는 볼트 암호화 및 복호화에 사용되는 솔트를 제공할 수 있습니다.
 *        그렇지 않으면 임의의 솔트가 생성됩니다. * hdPathString(필수): 사용자는 다음을 제공해야 합니다. BIP39준거 HD 패스 문자열.
 *                    지금까지 기본값은 다음과 같습니다.
 *                    m/0'/0'/0'또 다른 일반적인 것은 BIP44 패스 문자열입니다.
 *                    m/44'/60'/0'/0.
 */

const createWallet = (req, res) => {
  let password = req.password;
  let mnemonic = req.mnemonic;
  console.log('Contract :: ', req);
  try {
    //ceiling wine else supreme ketchup invite derive weekend alpha seat ranch goddess
    const resDataSend = new Promise((resolve, reject) => {
      keystore.createVault(
        {
          password: password,
          seedPhrase: mnemonic,
          hdPathString: "",
        },
        (err, ks) => {
          ks.keyFromPassword(password, (err, pwDerivedKey) => {
            ks.generateNewAddress(pwDerivedKey, 1);

            const address = ks.getAddresses().toString();
            const privateKey = ks.exportPrivateKey(address, pwDerivedKey);
            const seed = ks.getSeed(pwDerivedKey);

            const resData = {
              resultCode: 'success',
              address,
              privateKey,
              seed,
            };
            resolve(resData);
          });
        }
      );
    });
    return resDataSend;
  } catch (e) {
    appErrorLog(
      'response',
      req.toWalletAddress,
      e.error.message
    );
    let error_msg = e.error.message;
    return {
      resultCode: '9999',
      resultData: error_msg,
    };
  }
};

/**
 * ether 수량 확인
 */
const etherBalance = async walletAddress => {
  try {
    const provider = new ethers.providers.JsonRpcProvider(process.env.RPC_URL);
    const balance = await provider.getBalance(walletAddress);
    const etherAmount = ethers.utils.formatEther(balance);
    return {
      resultCode: 'success',
      etherAmount,
    };
  } catch (e) {
    appErrorLog(
      'response',
      req.toWalletAddress,
      e.error.message
    );
    let error_msg = e.error.message;
    return {
      resultCode: '9999',
      resultData: error_msg,
    };
  }
};

/**
 * paxb 수량 확인
 */
const getBalanceOf = async walletAddress => {
  try {
    const provider = new ethers.providers.JsonRpcProvider(process.env.RPC_URL);

    // token contract
    const tokenContract = new ethers.Contract(
      process.env.PXBT_CONTRACT_ADDRESS,
      contractAbi.abi,
      provider
    );

    const balancePxbt = await tokenContract.balanceOf(walletAddress);
    const pxbtAmount = ethers.utils.formatEther(balancePxbt);
    return {
      resultCode: 'success',
      pxbtAmount,
    };
  } catch (e) {
    appErrorLog(
      'response',
      req.toWalletAddress,
      e.error.message
    );
    let error_msg = e.error.message;
    return {
      resultCode: '9999',
      resultData: error_msg,
    };
  }
};

/**
 * ether 전송  : Account > MXUP 지갑으로 전송
 */
const etherTransferMxup = async req => {
  try { 
    const fromPrivateKey = process.env.OWNER_PRIVATEKEY; // owner 개인 키
    const provider = new ethers.providers.JsonRpcProvider(process.env.RPC_URL);
    const signer = new ethers.Wallet(fromPrivateKey, provider);

    const transaction = await signer.sendTransaction({
      to: req.toWalletAddress,
      value: ethers.utils.parseEther(req.atAmount), // ether 금액
    });

    const responseData = {
      hash: transaction.hash,
      from: transaction.from,
      to: transaction.to,
      value: ethers.utils.formatEther(transaction.value),
    };
    return {
      resultCode: 'success',
      responseData,
    };
  } catch (e) {
    appErrorLog(
      'response',
      req.toWalletAddress,
      e.error.message
    );
    let error_msg = e.error.message;
    return {
      resultCode: '9999',
      resultData: error_msg,
    };
  }
};

/**
 * pxbt 전송 : Account > MXUP 지갑으로 전송
 */
const pxbtTransferMxup = async req => {
  try {
    console.log('pxbtTransferMxup : ', req);
    const fromPrivateKey = process.env.OWNER_PRIVATEKEY; 
    const provider = new ethers.providers.JsonRpcProvider(process.env.RPC_URL);
    const signer = new ethers.Wallet(fromPrivateKey, provider);

    // token contract
    const tokenContract = new ethers.Contract(
      process.env.PXBT_CONTRACT_ADDRESS,
      process.env.NFT_CONTRACT_ADDRESS,
      contractAbi.abi,
      provider
    );

    // Transfer erc20 token
    const tokenSigner = tokenContract.connect(signer);

    // transfer
    const tokenAmount = ethers.utils.parseUnits(req.atAmount, 18);
    const transaction = await tokenSigner.transfer(
      req.toWalletAddress,
      tokenAmount
    );
    console.log('Token transfer정보 : ', transaction.hash);
    const responseData = {
      hash: transaction.hash,
      from: transaction.from,
      to: transaction.to,
      value: ethers.utils.formatEther(transaction.value),
    };
    return {
      resultCode: 'success',
      responseData,
    };
  } catch (e) {
    appErrorLog(
      'response',
      req.toWalletAddress,
      error_msg_org
    );
    let error_msg = error_msg_org;
    return {
      resultCode: '9999',
      resultData: error_msg,
    };
  }
};

/**
 * ether 전송  : MXUP > Account 지갑으로 전송
 */
const etherTransferAccount = async (req, code) => {
  try {
    const fromPrivateKey = req.privateKey; //유저 지갑 개인 키
    const provider = new ethers.providers.JsonRpcProvider(process.env.RPC_URL);
    const signer = new ethers.Wallet(fromPrivateKey, provider);

    let toAddress = process.env.OWNER_ADDRESS;
    // 외부지갑의 경우 .요청한 주소로 처리
    if (code === '320109') {
      toAddress = req.toWalletAddress;
    }

    const transaction = await signer.sendTransaction({
      to: toAddress, // Account owner 지갑 - 전송할 지갑주소
      value: ethers.utils.parseEther(req.atAmount), // ether 금액
    });

    const responseData = {
      hash: transaction.hash,
      from: transaction.from,
      to: transaction.to,
      value: ethers.utils.formatEther(transaction.value),
    };
    return {
      resultCode: 'success',
      responseData,
    };
  } catch (e) {
    appErrorLog(
      'response',
      req.toWalletAddress,
      e.error.message
    );
    let error_msg = e.error.message;
    return {
      resultCode: '9999',
      resultData: error_msg,
    };
  }
};

/**
 * pxbt 전송 : MXUP > Account 지갑으로 전송
 */
const pxbtTransferAccount = async (req, code) => {
  try {
    console.log('pxbtTransferAccount : ', req);
    const fromPrivateKey = req.privateKey; //유저 지갑 개인 키
    const provider = new ethers.providers.JsonRpcProvider(process.env.RPC_URL);
    const signer = new ethers.Wallet(fromPrivateKey, provider);

    let toAddress = process.env.OWNER_ADDRESS;
    // 외부지갑의 경우 .요청한 주소로 처리
    if (code === '320109') {
      toAddress = req.toWalletAddress;
    }

    // token contract
    const tokenContract = new ethers.Contract(
      process.env.PXBT_CONTRACT_ADDRESS,
      contractAbi.abi,
      provider
    );

    // Transfer erc20 token
    const tokenSigner = tokenContract.connect(signer);
    // transfer
    const tokenAmount = ethers.utils.parseUnits(req.atAmount, 18);
    const transaction = await tokenSigner.transfer(
      toAddress, // 받는 지갑주소
      tokenAmount // 전송금액
    );
    console.log('Token transfer  : ', transaction);

    const responseData = {
      hash: transaction.hash,
      from: transaction.from,
      to: transaction.to,
      value: ethers.utils.formatEther(transaction.value),
    };
    return {
      resultCode: 'success',
      responseData,
    };
  } catch (e) {
    appErrorLog(
      'response',
      req.toWalletAddress,
      e.error.message
    );
    let error_msg = e.error.message;
    return {
      resultCode: '9999',
      resultData: error_msg,
    };
  }
};

module.exports = {
  newMnemonic,
  createWallet,
  etherBalance,
  getBalanceOf,
  etherTransferMxup,
  pxbtTransferMxup,
  etherTransferAccount,
  pxbtTransferAccount,
};
