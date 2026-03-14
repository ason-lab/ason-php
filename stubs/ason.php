<?php
/**
 * ASON PHP Extension - IDE Stubs
 *
 * High-performance ASON (Array-Schema Object Notation) codec.
 * SIMD-accelerated (SSE2/AVX2/NEON), zero-copy parsing, direct Zend API.
 *
 * @version 1.0.0
 */

/**
 * Encode a PHP value to ASON format.
 *
 * Associative arrays are serialized as structs: {field1,field2}:(val1,val2)
 * Sequential arrays of associative arrays are serialized as vec: [{field1,field2}]:(v1,v2),(v3,v4)
 * Sequential arrays of scalars are serialized as: [v1,v2,v3]
 *
 * @param mixed $data The data to encode (array, string, int, float, bool, null)
 * @return string ASON-encoded string
 * @throws \Exception on encoding failure
 */
function ason_encode(mixed $data): string { return ""; }

/**
 * Decode an ASON string to a PHP value.
 *
 * Supports both schema-prefixed: {field@type}:(val1,val2)
 * and vec format: [{field@type}]:(v1,v2),(v3,v4)
 *
 * Legacy map syntax like <str,int> is not supported.
 *
 * @param string $input ASON-formatted string
 * @return mixed Decoded PHP value (array, string, int, float, bool, null)
 * @throws \Exception on parse error
 */
function ason_decode(string $input): mixed { return null; }

/**
 * Encode a PHP value to ASON binary format.
 *
 * Binary format uses little-endian fixed-width encoding:
 * - int: 8 bytes LE
 * - float: 8 bytes LE (IEEE 754)
 * - bool: 1 byte (0 or 1)
 * - string: u32 length (LE) + UTF-8 bytes
 * - array: u32 count (LE) + N × element encoding
 *
 * @param mixed $data The data to encode
 * @return string Binary-encoded string
 * @throws \Exception on encoding failure
 */
function ason_encodeBinary(mixed $data): string { return ""; }

/**
 * Decode an ASON binary string using a type schema.
 *
 * Schema is an associative array mapping field names to type strings:
 * ['id' => 'int', 'name' => 'str', 'active' => 'bool']
 *
 * Supported types: 'int', 'float', 'bool', 'str'
 *
 * @param string $input Binary-encoded ASON string
 * @param array $schema Type schema for decoding
 * @return mixed Decoded PHP value
 * @throws \Exception on decode error
 */
function ason_decodeBinary(string $input, array $schema): mixed { return null; }

/**
 * Encode a PHP value to ASON format with type annotations.
 *
 * Like ason_encode() but includes type hints in the schema:
 * {id@int,name@str,active@bool}:(1,Alice,true)
 *
 * @param mixed $data The data to encode
 * @return string ASON-encoded string with type annotations
 * @throws \Exception on encoding failure
 */
function ason_encodeTyped(mixed $data): string { return ""; }

/**
 * Encode a PHP value to pretty-formatted ASON.
 *
 * Applies smart indentation: short structures stay inline,
 * long structures are expanded with 2-space indentation.
 *
 * @param mixed $data The data to encode
 * @return string Pretty-formatted ASON string
 * @throws \Exception on encoding failure
 */
function ason_encodePretty(mixed $data): string { return ""; }

/**
 * Encode a PHP value to pretty-formatted ASON with type annotations.
 *
 * Combines encodePretty and encodeTyped: pretty-formatted with type hints.
 *
 * @param mixed $data The data to encode
 * @return string Pretty-formatted ASON string with type annotations
 * @throws \Exception on encoding failure
 */
function ason_encodePrettyTyped(mixed $data): string { return ""; }
