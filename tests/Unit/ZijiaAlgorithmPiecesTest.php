<?php

namespace Tests\Unit;

use App\Services\SmartFreezer\RecognitionVerdict;
use App\Services\SmartFreezer\Zijia\RecognitionRequest;
use App\Services\SmartFreezer\Zijia\RecognitionResult;
use App\Services\SmartFreezer\Zijia\ZijiaSigner;
use App\Services\SmartFreezer\Zijia\ZijiaVideoPush;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The pure parts of the Zijia algorithm integration: signature, the two envelopes' business
 * content, the video-push parser and the verdict.
 */
class ZijiaAlgorithmPiecesTest extends TestCase
{
    // ------------------------------------------------------------------ signature (§3.2)

    public function test_sign_is_md5_of_the_sorted_pairs_plus_the_key_in_upper_case(): void
    {
        $params = [
            'timestamp' => '2026-09-27 10:00:00', 'method' => 'dynamic.cabinet.add.queue', 'version' => 'v1',
            'appId' => '1789379222883159', 'signType' => 'md5', 'bizContent' => '{"a":1}',
        ];
        $canonical = 'appId=1789379222883159&bizContent={"a":1}&method=dynamic.cabinet.add.queue'
            .'&signType=md5&timestamp=2026-09-27 10:00:00&version=v1&key=SECRET';

        $this->assertSame(strtoupper(md5($canonical)), (new ZijiaSigner('SECRET'))->sign($params));
    }

    public function test_sign_leaves_out_sign_itself_and_empty_values(): void
    {
        $signer = new ZijiaSigner('K');
        $bare = ['a' => '1', 'b' => '2'];

        $this->assertSame($signer->sign($bare), $signer->sign($bare + ['sign' => 'X', 'empty' => '', 'none' => null]));
    }

    public function test_verify_accepts_the_right_signature_in_any_case_and_nothing_else(): void
    {
        $signer = new ZijiaSigner('K');
        $params = ['appId' => '1', 'method' => 'cabinet.algorithm.order.result', 'bizContent' => '{"tradeId":"T"}'];
        $sign = $signer->sign($params);

        $this->assertTrue($signer->verify($params + ['sign' => $sign]));
        $this->assertTrue($signer->verify($params + ['sign' => strtolower($sign)]));
        $this->assertFalse($signer->verify($params + ['sign' => str_repeat('0', 32)]));
        $this->assertFalse($signer->verify($params));
        // A changed business payload breaks it: bizContent is signed as sent.
        $this->assertFalse($signer->verify(['bizContent' => '{"tradeId":"U"}'] + $params + ['sign' => $sign]));
        $this->assertFalse((new ZijiaSigner('other'))->verify($params + ['sign' => $sign]));
    }

    // ------------------------------------------------------------------ request / result

    public function test_a_recognition_request_carries_the_documented_business_fields(): void
    {
        $request = new RecognitionRequest('JS093016', 'SF-2009-1789000000-4', ['https://v/1.mp4'], ['6925303751401', '6925303751401', '690123'],
            ['model-a'], 'https://sys.example/notify', 30, 1);

        $this->assertSame([
            'modelIdList' => ['model-a'],
            'videoList' => ['https://v/1.mp4'],
            'goodsList' => [['positions' => [], 'sn' => '6925303751401'], ['positions' => [], 'sn' => '690123']],
            'notifyUrl' => 'https://sys.example/notify',
            'orderStatus' => 0,
            'deviceId' => 'JS093016',
            'tradeId' => 'SF-2009-1789000000-4',
            'doorId' => 1,
            'videoDuration' => 30,
        ], $request->bizContent());
    }

    public function test_a_request_without_goods_is_refused_before_it_can_crash_their_server(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RecognitionRequest('D', 'T', ['https://v/1.mp4'], [], ['m'], 'https://n');
    }

    public function test_a_result_adds_up_repeated_codes_and_names_its_status(): void
    {
        $result = RecognitionResult::fromBizContent([
            'tradeId' => 'T1', 'orderStatus' => 0, 'errorMessage' => '', 'taskType' => '',
            'items' => [['code' => '111', 'number' => 1], ['code' => '111', 'number' => 2], ['code' => '222', 'number' => 1], ['code' => '', 'number' => 5]],
        ]);

        $this->assertTrue($result->isNormal());
        $this->assertSame(['111' => 3, '222' => 1], $result->items);
        $this->assertNull($result->errorMessage);
        $this->assertSame('video error / frames lost', RecognitionResult::fromBizContent(['tradeId' => 'T', 'orderStatus' => 401])->statusLabel());
        $this->assertSame('status 999', RecognitionResult::fromBizContent(['tradeId' => 'T', 'orderStatus' => 999])->statusLabel());
        // Live callback 2026-09-30: the finer reason rides in the undocumented jsOrderStatus.
        $live = RecognitionResult::fromBizContent(['tradeId' => 'SDK1790732333409', 'orderStatus' => 501, 'jsOrderStatus' => 503, 'jsOrderStatusName' => '商品未上架', 'items' => []]);
        $this->assertSame('recognition error — 503 goods not listed in the model (商品未上架)', $live->statusLabel());
        $this->assertSame('normal', RecognitionResult::fromBizContent(['tradeId' => 'T', 'orderStatus' => 0, 'jsOrderStatus' => 0, 'jsOrderStatusName' => '正常'])->statusLabel());
        $this->assertSame('recognition error — 777 新原因', RecognitionResult::fromBizContent(['tradeId' => 'T', 'orderStatus' => 501, 'jsOrderStatus' => 777, 'jsOrderStatusName' => '新原因'])->statusLabel());
    }

    public function test_a_result_without_a_trade_id_is_unusable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RecognitionResult::fromBizContent(['orderStatus' => 0, 'items' => []]);
    }

    // ------------------------------------------------------------------ video push

    public function test_a_push_yields_imei_trade_videos_duration_and_door(): void
    {
        $push = ZijiaVideoPush::fromPayload([
            'data' => [
                'imei' => '861232069528880',
                'deviceNo' => 'JS093016',
                'tradeId' => 'ZSA769242319-251218135320244',
                'orderNo' => 'SF-50001-1789000000-3',
                'cover' => 'https://oss.example.cn/cover.jpg',
                'videos' => [['url' => 'https://oss.example.cn/cam1.mp4?Expires=1'], ['url' => 'https://oss.example.cn/cam2.mp4']],
                'videoDuration' => '29.6',
                'doorId' => '1',
            ],
        ]);

        $this->assertSame('861232069528880', $push->imei);
        $this->assertSame('JS093016', $push->deviceNo);
        $this->assertSame('ZSA769242319-251218135320244', $push->tradeId);
        $this->assertSame('SF-50001-1789000000-3', $push->sessionRef);
        $this->assertSame('50001', $push->sessionVendCode());
        // The cover image is not a video once real videos are present.
        $this->assertSame(['https://oss.example.cn/cam1.mp4?Expires=1', 'https://oss.example.cn/cam2.mp4'], $push->videoUrls);
        $this->assertSame(30, $push->videoDuration);
        $this->assertSame(1, $push->doorId);
        $this->assertSame('861232069528880', $push->deviceIdentifier());
    }

    public function test_our_session_ref_stands_in_for_the_trade_id_and_bare_urls_are_kept(): void
    {
        $push = ZijiaVideoPush::fromPayload(['note' => 'order SF-2009-1789000000-4 closed', 'url' => 'https://oss.example.cn/stream/abc']);

        $this->assertNull($push->imei);
        $this->assertSame('SF-2009-1789000000-4', $push->tradeId);
        $this->assertSame(['https://oss.example.cn/stream/abc'], $push->videoUrls);
        $this->assertSame(0, $push->videoDuration);
        $this->assertSame(1, $push->doorId);
    }

    public function test_their_trade_id_wins_whatever_order_the_payload_lists_it_in(): void
    {
        // The documented example lists our orderNo BEFORE their tradeId.
        $push = ZijiaVideoPush::fromPayload([
            'imei' => '861232069528880',
            'orderNo' => 'SF-50001-1789000000-3',
            'tradeId' => 'ZSA769242319-251218135320244',
            'goods' => [['sn' => '6925303751401']],
        ]);

        $this->assertSame('ZSA769242319-251218135320244', $push->tradeId);
        $this->assertSame('SF-50001-1789000000-3', $push->sessionRef);
        // A goods serial is never taken for the device.
        $this->assertNull($push->deviceNo);
    }

    public function test_their_live_push_shape_yields_their_order_no_as_the_trade(): void
    {
        // Verbatim shape of their first real pushes, 2026-09-30 (test cabinet JS093519).
        $push = ZijiaVideoPush::fromPayload(json_decode('{"orderNo":"SDK1790732333409","method":"video",'
            .'"video1":{"videoDuration":0,"videoUrl":"https://jishi1.oss-cn-hangzhou.aliyuncs.com/boxapp/JS093519/order/SDK1790732333409/SDK1790732333409-d1c3-25f-1280x720.mp4","videoFrames":0,"videoSize":0},'
            .'"video2":{"videoDuration":0,"videoUrl":"https://jishi1.oss-cn-hangzhou.aliyuncs.com/boxapp/JS093519/order/SDK1790732333409/SDK1790732333409-d1c4-25f-1280x720.mp4","videoFrames":0,"videoSize":0},'
            .'"pullDoor":1,"deviceNo":"JS093519","doorId":1,"sign":"D2318D939A9331E6F51E0B4DCC605B4F"}', true));

        $this->assertSame('SDK1790732333409', $push->tradeId);
        $this->assertNull($push->sessionRef);
        $this->assertSame('JS093519', $push->deviceNo);
        $this->assertSame('JS093519', $push->deviceIdentifier());
        $this->assertCount(2, $push->videoUrls);
        $this->assertSame(0, $push->videoDuration);
        $this->assertSame(1, $push->doorId);
    }

    public function test_an_order_no_holding_our_ref_is_not_their_trade(): void
    {
        $push = ZijiaVideoPush::fromPayload(['orderNo' => 'SF-50001-1789000000-3', 'deviceNo' => '861232069528880']);

        $this->assertSame('SF-50001-1789000000-3', $push->tradeId);
        $this->assertSame('SF-50001-1789000000-3', $push->sessionRef);
    }

    // ------------------------------------------------------------------ verdict

    public function test_verdict_match(): void
    {
        $v = RecognitionVerdict::compare([10 => 2, 11 => 1], ['111' => 2, '222' => 1], ['111' => 10, '222' => 11]);

        $this->assertSame(RecognitionVerdict::MATCH, $v->outcome);
        $this->assertSame([0, 0], array_column($v->lines, 'delta'));
    }

    public function test_verdict_took_more_is_unpaid_goods(): void
    {
        $v = RecognitionVerdict::compare([10 => 1], ['111' => 1, '222' => 1], ['111' => 10, '222' => 11]);

        $this->assertSame(RecognitionVerdict::TOOK_MORE, $v->outcome);
        $this->assertSame(['product_id' => 11, 'code' => '222', 'paid' => 0, 'taken' => 1, 'delta' => 1], $v->lines[1]);
    }

    public function test_verdict_took_less_and_mixed(): void
    {
        $this->assertSame(RecognitionVerdict::TOOK_LESS, RecognitionVerdict::compare([10 => 2], ['111' => 1], ['111' => 10])->outcome);
        $this->assertSame(RecognitionVerdict::TOOK_LESS, RecognitionVerdict::compare([10 => 1], [], ['111' => 10])->outcome);
        $this->assertSame(RecognitionVerdict::MIXED, RecognitionVerdict::compare([10 => 1], ['222' => 1], ['111' => 10, '222' => 11])->outcome);
    }

    public function test_a_code_no_product_carries_makes_the_verdict_unrecognised(): void
    {
        $v = RecognitionVerdict::compare([10 => 1], ['111' => 1, '999' => 1], ['111' => 10]);

        $this->assertSame(RecognitionVerdict::UNRECOGNISED, $v->outcome);
        $this->assertSame(['999'], $v->unknownCodes);
        $lines = $v->lines;
        $this->assertSame(['product_id' => null, 'code' => '999', 'paid' => 0, 'taken' => 1, 'delta' => 1], end($lines));
    }

    public function test_a_paid_product_the_algorithm_could_not_name_makes_the_verdict_incomplete(): void
    {
        // Product 12 was paid for but has no barcode, so it was never a candidate: its "0 taken" is
        // not evidence the customer is owed anything.
        $v = RecognitionVerdict::compare([10 => 1, 12 => 1], ['111' => 1], ['111' => 10]);

        $this->assertSame(RecognitionVerdict::INCOMPLETE, $v->outcome);
        $this->assertSame([12], $v->unnameable);

        // A slot with no product (goods_id 0) is just as unknowable.
        $this->assertSame(RecognitionVerdict::INCOMPLETE, RecognitionVerdict::compare([0 => 1], [], ['111' => 10])->outcome);
    }
}
