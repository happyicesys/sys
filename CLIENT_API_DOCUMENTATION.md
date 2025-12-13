# Client API Documentation

## Authentication
All API requests require a **Bearer Token** in the `Authorization` header.
```
Authorization: Bearer <YOUR_ACCESS_TOKEN>
```

---

## 1. Get Transactions
Retrieve a paginated list of vending transactions.

**Endpoint:** `POST /api/v1/client/transactions`

### Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `date_from` | `Date` (YYYY-MM-DD) | **Yes** | Start date of the range. |
| `date_to` | `Date` (YYYY-MM-DD) | **Yes** | End date of the range. **Max range: 12 months.** |
| `codes` | `String` | No | Comma-separated list of machine codes (e.g., `1001,1002`). |
| `order_id` | `String` | No | Filter by specific Order ID. |
| `page` | `Integer` | No | Page number (default: 1). |
| `per_page` | `Integer` | No | Items per page (default: 50, max: 100). |

**Note:** Any other parameters found in the request will be ignored.

### Example Request
```json
{
    "date_from": "2023-10-01",
    "date_to": "2023-10-31",
    "codes": "1001,1002",
    "per_page": 20
}
```

### Response Attributes
*   `amount`: Transaction amount.
*   `order_id`: Unique order identifier.
*   `payment_method`: Method of payment (e.g., Cash, Cashless).
*   `product_id`: Product code.
*   `product_name`: Product name.
*   `transaction_datetime`: Date and time of transaction.
*   `vend_id`: Machine code.
*   `vend_name`: Machine name / Customer name.
*   `channel`: Vend channel code.
*   `error`: Error code (if any).

---

## 2. Get Machine Status
Retrieve the current status and stock levels of your machines.

**Endpoint:** `POST /api/v1/client/machine-status`

### Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `codes` | `String` | No | Comma-separated list of machine codes (e.g., `1001,1002`). |
| `page` | `Integer` | No | Page number (default: 1). |
| `per_page` | `Integer` | No | Items per page (default: 50). |

**Constraints:**
*   Only returns **Active** machines.
*   Any other parameters (`is_online`, etc.) are ignored.

### Example Request
```json
{
    "codes": "1001"
}
```

### Response Attributes
*   `code`: Machine code.
*   `name`: Machine name.
*   `is_online`: Connection status (`true` / `false`).
*   `is_door_open`: Door status (`Yes` / `No`).
*   `is_temp_error`: Temperature error status.
*   `last_updated_at`: Last seen online (relative time).
*   `channels`: List of channels in the machine.
    *   `code`: Channel code (e.g., 10).
    *   `product`: Product details (code, name).
    *   `qty`: Current quantity.
    *   `capacity`: Max capacity.
