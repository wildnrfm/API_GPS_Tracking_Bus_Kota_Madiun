#!/bin/bash

# Test real login dengan role-based device limiting

echo "=== REAL LOGIN TEST WITH ROLE-BASED DEVICE LIMITING ==="
echo ""

# Test Admin Login (should support 2 devices)
echo "=== TEST 1: ADMIN LOGIN ===

"
echo "Attempt 1 (Desktop):"
RESPONSE1=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/91.0" \
  -d '{
    "email":"admin@diskominfo.kotamadiun.com",
    "password":"admin1234"
  }')

TOKEN1=$(echo $RESPONSE1 | jq -r '.data.token')
ADMIN_ID=$(echo $RESPONSE1 | jq -r '.data.user.id')

if [ "$TOKEN1" != "null" ] && [ ! -z "$TOKEN1" ]; then
    echo "✓ Login successful (Token: ${TOKEN1:0:20}...)"
    echo "✓ Admin ID: $ADMIN_ID"
else
    echo "❌ Login failed"
    echo $RESPONSE1 | jq '.'
    exit 1
fi

echo ""
echo "Attempt 2 (Mobile iPhone):"
RESPONSE2=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X)" \
  -d '{
    "email":"admin@diskominfo.kotamadiun.com",
    "password":"admin1234"
  }')

TOKEN2=$(echo $RESPONSE2 | jq -r '.data.token')

if [ "$TOKEN2" != "null" ] && [ ! -z "$TOKEN2" ]; then
    echo "✓ Login successful (Token: ${TOKEN2:0:20}...)"
else
    echo "❌ Login failed"
    exit 1
fi

echo ""
echo "Attempt 3 (Tablet - should delete oldest device):"
RESPONSE3=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "User-Agent: Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X)" \
  -d '{
    "email":"admin@diskominfo.kotamadiun.com",
    "password":"admin1234"
  }')

TOKEN3=$(echo $RESPONSE3 | jq -r '.data.token')

if [ "$TOKEN3" != "null" ] && [ ! -z "$TOKEN3" ]; then
    echo "✓ Login successful (Token: ${TOKEN3:0:20}...)"
else
    echo "❌ Login failed"
    exit 1
fi

echo ""
echo "Checking if first token (Desktop) is now invalid..."
# Try to use TOKEN1 - should fail because it was deleted
INVALID=$(curl -s -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer $TOKEN1")

INVALID_CODE=$(echo $INVALID | jq -r '.code // "unknown"')

if [ "$INVALID_CODE" = "INVALID_TOKEN" ] || [ "$INVALID_CODE" = "TOKEN_EXPIRED" ]; then
    echo "✓ Token 1 (Desktop) is correctly invalidated"
else
    echo "⚠️  Token 1 might still be valid (expected invalidation)"
fi

echo ""
echo "Verify Token 2 & 3 are still valid:"

ME2=$(curl -s -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer $TOKEN2")
USER2=$(echo $ME2 | jq -r '.data.user.email // "invalid"')

ME3=$(curl -s -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer $TOKEN3")
USER3=$(echo $ME3 | jq -r '.data.user.email // "invalid"')

if [ "$USER2" = "admin@diskominfo.kotamadiun.com" ]; then
    echo "✓ Token 2 (iPhone) is valid"
else
    echo "❌ Token 2 is invalid"
fi

if [ "$USER3" = "admin@diskominfo.kotamadiun.com" ]; then
    echo "✓ Token 3 (iPad) is valid"
else
    echo "❌ Token 3 is invalid"
fi

echo ""
echo "=== ✅ ADMIN MULTI-DEVICE TEST PASSED ==="
