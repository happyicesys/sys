"""Prints the text of the first QR code in an image file; exit 1 when there is none.

mark1's fallback QR reader (PaymentGatewayService::readQrText). zxing-cpp reads the PayNow QRs with
the logo that the PHP decoder cannot (Omise, since 2026-10-07). Runs in its own venv on prod:
    uv venv ~/.local/qrdecode && VIRTUAL_ENV=~/.local/qrdecode uv pip install zxing-cpp pillow
"""
import sys

import zxingcpp
from PIL import Image

results = zxingcpp.read_barcodes(Image.open(sys.argv[1]).convert("RGB"), formats=zxingcpp.BarcodeFormat.QRCode)
for result in results:
    if result.text:
        sys.stdout.write(result.text)
        sys.exit(0)
sys.exit(1)
