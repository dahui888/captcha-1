<?php
declare(strict_types=1);

namespace Fastknife\Service;


use Fastknife\Domain\Factory;
use Fastknife\Domain\Logic\Cache;
use Fastknife\Domain\Logic\WordData;
use Fastknife\Domain\Logic\WordImage;
use Fastknife\Exception\ParamException;
use Fastknife\Utils\AesUtils;
use Fastknife\Utils\RandomUtils;

class ClickWordCaptchaService extends Service
{
    /**
     * 获取文字验证码
     */
    public function get()
    {
        $cacheEntity = new Cache($this->config['cache']);
        $wordImage = Factory::make('word', $this->config);
        //执行创建
        $wordImage->run();
        $data = [
            'originalImageBase64' => $wordImage->response(),
            'secretKey' => RandomUtils::getRandomCode(16, 3),
            'token' => RandomUtils::getUUID(),
            'wordList' => $wordImage->getWordList()
        ];
        //缓存
        $cacheEntity->set($data['token'], [
            'secretKey' => $data['secretKey'],
            'pointList' => $wordImage->getPoint()
        ]);
        return $data;
    }

    public function check($token, $pointJson)
    {
        $cacheEntity = new Cache($this->config['cache']);
        $wordData = new WordData();
        $originData = $cacheEntity->get($token);
        $cacheEntity->delete($token);
        if (empty($originData)) {
            throw new ParamException('参数校验失败：token');
        }

        $originPointList = $originData['pointList'];
        $secretKey = $originData['secretKey'];
        $pointJson = AesUtils::decrypt($pointJson, $secretKey);
        if ($pointJson === false) {
            throw new ParamException('aes验签失败！');
        }
        $targetPointList = $wordData->array2Point(json_decode($pointJson, true));
        $wordData->check($originPointList, $targetPointList);
    }
}
