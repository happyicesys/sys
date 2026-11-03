# Zijia SKU links — why registered SKUs missed the AI, and what stops it (2026-11-03)

Brian: 18 products on 50001, only 13 sent to the AI, though Zijia's portal shows them approved.

## What was wrong

`goodsList` is built from `products.barcode` only (`FreezerRecognitionService::candidateCodes`).
`zijia_sku_applications` is a mirror and plays no part, so importing it would have changed nothing.
There was no screen to set a barcode either: only AI Training's approval and the 3-minute sync write it.

The sync searched Zijia's library with our exact product name. Their search is a substring match, so:

| Our name | Their name | Result |
|---|---|---|
| Milk & Musang King Durian Ice Cream cup | Milk & MusangKing IceCreamcup | 0 hits, forever |
| 24K Gold Classic Chocolate Eclair | 24K Gold Classic Choc Eclair | 0 hits, forever |
| Mao Shan Wang Durian - family pack | Mao Shan Wang Durian-family pack | 0 hits: the punctuation rule never got to run |

The 08:30 email then said "not in Zijia's library yet", which was false, and most of its lines were
retired planogram rows. All 26 applications in mark1 are `source = vms4`: staff register in the portal,
and a portal approval sends no callback.

La Pistache, Unicornetto and Strawberry Matcha were NOT this bug: they were registered at 19:52–20:02 on
2026-11-03 and linked themselves within minutes (session 35 at 19:42 sent 13 codes, session 36 at 20:08 sent 16).

On 2026-11-03 Brian approved linking by hand: OK-01 → `202610051`, E-01 → `2026110301`. The portal shows
"1" as OK-01's 69码, but their library lists it as `202610051`; code `1` is another SKU (红球). Session 37
(50001, 21:21) then sent all 18 codes and Zijia accepted it, verdict `match`, so both codes are valid. Neither
product was in that sale, so the AI naming them correctly is still unproven.

## What changed

- **`ZijiaLibraryMirror`** keeps the recent end of their library in `zijia_library_skus`: 3 calls a run,
  a full walk back to 2026-09 once a day, which also drops entries they removed. It relies on the list
  being oldest first, as observed; the daily walk is the safety net.
- **`ZijiaBarcodeSync`** compares names against that copy as well as their search. A unique exact name
  links by itself, also for a product on no freezer yet (only if that name is one product's in the whole
  table). Servers with no smart freezer (lsh, IDN) make no call.
- **`ZijiaSkuLinks`** is the one coverage rule, read by:
  AI Recognition (per-freezer count), **SKU Links** (`/ai-recognition/sku-links`), Needs Attention (group
  "Freezer products not on the AI's list") and the 08:30 email (active products only, with the real reason).
  The planogram editor marks each product with no barcode.
- **SKU Links** lets a person confirm a near match. It never links by score: a wrong barcode makes the AI
  name the wrong product and can charge the wrong amount. It re-checks their live library, never replaces a
  barcode and never puts one code on two products. Viewing = `read ai-recognition`; linking = `update products`.
  Their library is shared, so only the unrestricted operator sees its list.
- A barcode their library stopped listing is now reported. It is worse than no barcode: they refuse the whole session.

Not changed: `candidateCodes`, verdicts, charging.

## Known limits

- Library entries named in Chinese are not offered for review (they are nearly all other customers'); exact names still match.
- A near match needs a person. Only Zijia can remove that step (below).

## Request to Zijia (to send)

> 您好，我们的冰柜需要把门店商品和贵司商品库准确对应。目前 `sys_sku/v1/query_list` 只返回商品名称和条码，
> 没有我们在门户「商品申请」里填写的**商品编码**，所以只能靠名称匹配，名称稍有不同就对应不上。
>
> 想请贵司支持以下任一方案：
> 1. 在 `query_list` 的返回结果中增加**商品编码**字段；或
> 2. 提供接口，按我们的账号（海外部门）查询已通过审核的商品申请，包括商品编码和条码；或
> 3. 门户审核通过后，也按算法服务接口文档第 7 节（商品审批回调）通知我们。
>
> 另外想确认：门户里 69 码显示为「1」的商品（Milk & MusangKing IceCreamcup），商品库中的 productCode 是
> 202610051，识别时 `goodsList.sn` 传 202610051 是否正确？谢谢！

English: `query_list` returns only name and barcode, not the 商品编码 staff type in the portal, so we can only
match on names. Please add 商品编码 to `query_list`, or give us an API listing our department's approved
applications, or send the §7 approval callback for portal approvals too. Please also confirm that `sn` =
202610051 is right for the SKU whose portal 69码 reads "1".
