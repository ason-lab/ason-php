#pragma once
#include "asun_simd.h"
#include <string>
#include <cstring>
#include <cmath>
#include <cstdio>
#include <charconv>
#include <stdexcept>
#include <vector>
#include <string_view>

namespace asun_core {

class Error : public std::runtime_error {
public:
    explicit Error(const std::string& msg) : std::runtime_error(msg) {}
};

// ---- Fast integer/float formatting ----
static constexpr char DEC_DIGITS[201] =
    "0001020304050607080910111213141516171819"
    "2021222324252627282930313233343536373839"
    "4041424344454647484950515253545556575859"
    "6061626364656667686970717273747576777879"
    "8081828384858687888990919293949596979899";

inline void append_u64(std::string& buf, uint64_t v) {
    if (v < 10) { buf.push_back('0' + (char)v); return; }
    if (v < 100) { buf.push_back(DEC_DIGITS[v*2]); buf.push_back(DEC_DIGITS[v*2+1]); return; }
    char tmp[20]; int i = 20;
    while (v >= 100) { auto r = v % 100; v /= 100; i -= 2; tmp[i] = DEC_DIGITS[r*2]; tmp[i+1] = DEC_DIGITS[r*2+1]; }
    if (v >= 10) { i -= 2; tmp[i] = DEC_DIGITS[v*2]; tmp[i+1] = DEC_DIGITS[v*2+1]; }
    else { tmp[--i] = '0' + (char)v; }
    buf.append(tmp + i, 20 - i);
}

inline void append_i64(std::string& buf, int64_t v) {
    if (v < 0) { buf.push_back('-'); append_u64(buf, (uint64_t)(-v)); } else append_u64(buf, (uint64_t)v);
}

inline void append_f64(std::string& buf, double v) {
    if (std::isnan(v) || std::isinf(v)) { buf.push_back('0'); return; }
    double intpart, frac = std::modf(v, &intpart);
    if (frac == 0.0) { auto iv = (int64_t)v; if ((double)iv == v) { append_i64(buf, iv); buf.append(".0", 2); return; } }
    double v10 = v * 10.0;
    if (v10 - std::trunc(v10) == 0.0 && std::abs(v10) < 1e18) {
        auto vi = (int64_t)v10; if (vi < 0) { buf.push_back('-'); vi = -vi; }
        append_u64(buf, (uint64_t)vi / 10); buf.push_back('.'); buf.push_back('0' + (char)((uint64_t)vi % 10)); return;
    }
    char tmp[32];
    auto [ptr, ec] = std::to_chars(tmp, tmp + 32, v);
    if (ec == std::errc()) {
        std::string_view sv(tmp, ptr - tmp); buf.append(sv.data(), sv.size());
        if (sv.find('.') == sv.npos && sv.find('e') == sv.npos) buf.append(".0", 2);
    } else { int n = std::snprintf(tmp, 32, "%.17g", v); buf.append(tmp, n); }
}

// ---- String helpers ----
inline bool needs_quoting(const char* s, size_t len) {
    if (len == 0) return true;
    {
        char f = s[0], e = s[len - 1];
        if (f == ' ' || f == '\t' || f == '\n' || f == '\r') return true;
        if (e == ' ' || e == '\t' || e == '\n' || e == '\r') return true;
    }
    if (len == 4 && memcmp(s, "true", 4) == 0) return true;
    if (len == 5 && memcmp(s, "false", 5) == 0) return true;
    // Structural characters covered by SIMD: ctrl, ',', '@', '(', ')', '[', ']', '"', '\\'.
    if (asun_simd::has_special_chars((const uint8_t*)s, len)) return true;
    // Additional structural characters and the comment-open digraph '/'+'*'.
    for (size_t i = 0; i < len; i++) {
        unsigned char b = static_cast<unsigned char>(s[i]);
        if (b == '{' || b == '}' || b == ':') return true;
        if (b == '/' && i + 1 < len && s[i + 1] == '*') return true;
    }
    // Number-lookalike: any leading-digit, '-DIGIT', '+DIGIT', '+.DIGIT',
    // '-.DIGIT', '.DIGIT' would be consumed by the number parser.
    unsigned char c0 = static_cast<unsigned char>(s[0]);
    if (c0 >= '0' && c0 <= '9') return true;
    if (c0 == '-' || c0 == '+') {
        if (len >= 2) {
            unsigned char c1 = static_cast<unsigned char>(s[1]);
            if ((c1 >= '0' && c1 <= '9') || c1 == '.') return true;
        }
    }
    if (c0 == '.' && len >= 2) {
        unsigned char c1 = static_cast<unsigned char>(s[1]);
        if (c1 >= '0' && c1 <= '9') return true;
    }
    return false;
}

inline void append_escaped(std::string& buf, const char* s, size_t len) {
    buf.push_back('"');
    size_t start = 0;
    while (start < len) {
        size_t pos = asun_simd::find_quote_or_special((const uint8_t*)s + start, len - start);
        buf.append(s + start, pos); start += pos;
        if (start >= len) break;
        switch ((uint8_t)s[start]) {
            case '"':  buf.append("\\\"", 2); break;
            case '\\': buf.append("\\\\", 2); break;
            case '\n': buf.append("\\n", 2); break;
            case '\t': buf.append("\\t", 2); break;
            case '\r': buf.append("\\r", 2); break;
            case '\b': buf.append("\\b", 2); break;
            case '\f': buf.append("\\f", 2); break;
            default: {
                uint8_t b = static_cast<uint8_t>(s[start]);
                if (b < 0x20 || b == 0x7f) {
                    char hex[7];
                    int n = std::snprintf(hex, sizeof(hex), "\\u%04x", b);
                    buf.append(hex, (size_t)n);
                } else {
                    buf.push_back(s[start]);
                }
                break;
            }
        }
        start++;
    }
    buf.push_back('"');
}

inline void append_str(std::string& buf, const char* s, size_t len) {
    if (needs_quoting(s, len)) append_escaped(buf, s, len); else buf.append(s, len);
}

inline bool schema_name_needs_quoting(const char* s, size_t len) {
    // Per ABNF:  bare-field-name = 1*( ALPHA / DIGIT / "_" )
    // Anything else (incl. '.', '-', '+', '@', ':', etc.) requires quoting.
    if (len == 0) return true;
    for (size_t i = 0; i < len; i++) {
        unsigned char b = static_cast<unsigned char>(s[i]);
        bool is_bare = (b >= 'A' && b <= 'Z')
                    || (b >= 'a' && b <= 'z')
                    || (b >= '0' && b <= '9')
                    || b == '_';
        if (!is_bare) return true;
    }
    return false;
}

inline void append_schema_name(std::string& buf, const char* s, size_t len) {
    if (schema_name_needs_quoting(s, len)) append_escaped(buf, s, len);
    else buf.append(s, len);
}

// ---- Parser ----
inline void skip_ws(const char*& p, const char* e) {
    while (p < e && (*p == ' ' || *p == '\t' || *p == '\n' || *p == '\r')) p++;
}

inline void skip_ws_comments(const char*& p, const char* e) {
    for (;;) {
        skip_ws(p, e);
        if (p + 1 < e && p[0] == '/' && p[1] == '*') {
            p += 2; while (p + 1 < e) { if (p[0] == '*' && p[1] == '/') { p += 2; break; } p++; }
        } else return;
    }
}

inline bool at_value_end(const char* p, const char* e) {
    if (p >= e) return true; char c = *p; return c == ',' || c == ')' || c == ']';
}

// Per ABNF: HEXDIG = DIGIT / "A"-"F" / "a"-"f".
// Returns -1 for non-hex bytes; callers MUST validate before consuming.
inline int hex_digit_value(char c) {
    if (c >= '0' && c <= '9') return c - '0';
    if (c >= 'A' && c <= 'F') return c - 'A' + 10;
    if (c >= 'a' && c <= 'f') return c - 'a' + 10;
    return -1;
}

// Decode an exact 4-HEXDIG block (already past the leading \u). Throws if any
// of the four bytes is not a HEXDIG. Advances `p` past the four hex digits and
// appends the UTF-8 encoding of the codepoint to `out`.
inline void decode_unicode_escape(const char*& p, const char* e, std::string& out) {
    if (p + 4 > e) throw Error("bad unicode escape: need 4 hex digits");
    int n0 = hex_digit_value(p[0]);
    int n1 = hex_digit_value(p[1]);
    int n2 = hex_digit_value(p[2]);
    int n3 = hex_digit_value(p[3]);
    if (n0 < 0 || n1 < 0 || n2 < 0 || n3 < 0) {
        throw Error("bad unicode escape: invalid hex digit");
    }
    unsigned long cp = (unsigned long)((n0 << 12) | (n1 << 8) | (n2 << 4) | n3);
    if (cp < 0x80) {
        out.push_back((char)cp);
    } else if (cp < 0x800) {
        out.push_back((char)(0xC0 | (cp >> 6)));
        out.push_back((char)(0x80 | (cp & 0x3F)));
    } else {
        out.push_back((char)(0xE0 | (cp >> 12)));
        out.push_back((char)(0x80 | ((cp >> 6) & 0x3F)));
        out.push_back((char)(0x80 | (cp & 0x3F)));
    }
    p += 4;
}

inline std::string parse_quoted(const char*& p, const char* e) {
    p++;
    const char* start = p;
    size_t off = asun_simd::find_quote_or_special((const uint8_t*)p, e - p);
    const char* sc = p + off;
    if (sc < e && *sc == '"') { p = sc + 1; return std::string(start, sc - start); }
    std::string r; if (sc > start) r.append(start, sc - start); p = sc;
    while (p < e) {
        char b = *p;
        if (b == '"') { p++; return r; }
        if (b == '\\') {
            p++; if (p >= e) throw Error("unclosed string");
            switch (*p) {
                case '"': r.push_back('"'); break; case '\\': r.push_back('\\'); break;
                case 'n': r.push_back('\n'); break; case 't': r.push_back('\t'); break;
                case 'r': r.push_back('\r'); break; case 'b': r.push_back('\b'); break;
                case 'f': r.push_back('\f'); break;
                case ',': r.push_back(','); break; case '(': r.push_back('('); break;
                case ')': r.push_back(')'); break; case '[': r.push_back('['); break;
                case ']': r.push_back(']'); break;
                case '{': r.push_back('{'); break; case '}': r.push_back('}'); break;
                case ':': r.push_back(':'); break; case '@': r.push_back('@'); break;
                case 'u': {
                    p++; // skip 'u' so decode_unicode_escape starts at 1st hex
                    decode_unicode_escape(p, e, r);
                    // we land 1-past-the-last-hex; outer loop expects to land
                    // ON the last consumed escape char so the trailing p++
                    // below moves to the next normal byte. Pre-decrement to
                    // counter that.
                    p--;
                    break;
                }
                default: throw Error(std::string("bad escape: \\") + *p);
            }
            p++;
        } else { r.push_back(b); p++; }
    }
    throw Error("unclosed string");
}

inline std::string parse_plain(const char*& p, const char* e) {
    const char* start = p; bool esc = false;
    while (p < e) { char b = *p; if (b == ',' || b == ')' || b == ']') break; if (b == '\\') { esc = true; p += 2; continue; } p++; }
    const char* ve = p;
    while (ve > start && (ve[-1] == ' ' || ve[-1] == '\t')) ve--;
    while (start < ve && (*start == ' ' || *start == '\t')) start++;
    if (!esc) return std::string(start, ve - start);
    std::string r; r.reserve(ve - start);
    for (const char* q = start; q < ve;) {
        if (*q == '\\') {
            q++; if (q >= ve) throw Error("eof in escape");
            switch (*q) {
                case ',': r.push_back(','); break; case '(': r.push_back('('); break;
                case ')': r.push_back(')'); break; case '[': r.push_back('['); break;
                case ']': r.push_back(']'); break; case '"': r.push_back('"'); break;
                case '{': r.push_back('{'); break; case '}': r.push_back('}'); break;
                case ':': r.push_back(':'); break; case '@': r.push_back('@'); break;
                case '\\': r.push_back('\\'); break; case 'n': r.push_back('\n'); break;
                case 't': r.push_back('\t'); break;
                case 'r': r.push_back('\r'); break; case 'b': r.push_back('\b'); break;
                case 'f': r.push_back('\f'); break;
                case 'u': {
                    q++; // skip 'u' so decode_unicode_escape starts at 1st hex
                    decode_unicode_escape(q, ve, r);
                    // Outer loop performs `q++` after this switch; counter it.
                    q--;
                    break;
                }
                default: throw Error(std::string("bad escape: \\") + *q);
            }
            q++;
        } else { r.push_back(*q); q++; }
    }
    return r;
}

inline std::string parse_string(const char*& p, const char* e) {
    skip_ws_comments(p, e); if (p >= e) return {};
    return (*p == '"') ? parse_quoted(p, e) : parse_plain(p, e);
}

inline void skip_value(const char*& p, const char* e) {
    skip_ws_comments(p, e); if (p >= e) return;
    if (*p == '"') { p++; while (p < e) { if (*p == '"') { p++; return; } if (*p == '\\') p++; p++; } return; }
    if (*p == '(' || *p == '[') {
        char o = *p, cl = (o == '(') ? ')' : ']'; int d = 0;
        while (p < e) { if (*p == o) d++; else if (*p == cl) { d--; if (d == 0) { p++; return; } } p++; }
        return;
    }
    while (p < e) { char b = *p; if (b == ',' || b == ')' || b == ']') return; p++; }
}

struct Schema {
    static constexpr int MAX = 64;
    std::string fields[MAX];
    int count = 0;
};

inline Schema parse_schema(const char*& p, const char* e);

inline void validate_schema_scalar_type(const char*& p, const char* e) {
    const char* start = p;
    while (p < e) {
        char b = *p;
        if (b == ',' || b == '}' || b == ']' || b == ' ' || b == '\t') break;
        p++;
    }
    std::string token(start, p - start);
    if (token.empty()) throw Error("expected schema type after '@'");
    if (!token.empty() && token.back() == '?') token.pop_back();
    if (token == "int" || token == "str" || token == "float" || token == "bool") return;
    throw Error("unsupported schema type '" + token + "'; use int, str, float, or bool");
}

inline void validate_schema_annotation(const char*& p, const char* e) {
    if (p >= e) throw Error("expected schema type after '@'");
    if (*p == '{') {
        (void)parse_schema(p, e);
        return;
    }
    if (*p == '[') {
        p++;
        skip_ws(p, e);
        if (p < e && *p == ']') {
            p++;
            return;
        }
        if (p < e && *p == '{') {
            (void)parse_schema(p, e);
        } else {
            validate_schema_scalar_type(p, e);
        }
        skip_ws(p, e);
        if (p >= e || *p != ']') throw Error("expected ']' in array type annotation");
        p++;
        return;
    }
    validate_schema_scalar_type(p, e);
}

inline Schema parse_schema(const char*& p, const char* e) {
    if (p >= e || *p != '{') throw Error("expected '{'");
    p++; Schema s;
    for (;;) {
        skip_ws(p, e); if (p >= e) throw Error("eof in schema");
        if (*p == '}') { p++; break; }
        if (s.count > 0) { if (*p != ',') throw Error("expected ','"); p++; skip_ws(p, e); }
        if (p < e && *p == '"') {
            auto name = parse_quoted(p, e);
            if (s.count < Schema::MAX) s.fields[s.count++] = std::move(name);
        } else {
            // Per ABNF: bare-field-name = 1*( ALPHA / DIGIT / "_" ).
            // Anything outside that set must be wrapped in a quoted-string.
            const char* st = p;
            while (p < e) {
                unsigned char b = static_cast<unsigned char>(*p);
                if (b == ',' || b == '}' || b == '@' || b == ' ' || b == '\t' ||
                    b == '\n' || b == '\r') break;
                bool is_bare = (b >= 'A' && b <= 'Z')
                            || (b >= 'a' && b <= 'z')
                            || (b >= '0' && b <= '9')
                            || b == '_';
                if (!is_bare) {
                    throw Error(std::string("invalid character in bare field-name: '")
                                + (char)b + "'");
                }
                p++;
            }
            if (p == st) throw Error("expected field-name");
            if (s.count < Schema::MAX) s.fields[s.count++] = std::string(st, p - st);
        }
        skip_ws(p, e);
        if (p < e && *p == '@') {
            p++; skip_ws(p, e);
            validate_schema_annotation(p, e);
        }
    }
    return s;
}

inline void skip_remaining(const char*& p, const char* e) {
    for (;;) {
        skip_ws_comments(p, e); if (p >= e || *p == ')') return;
        if (*p == ',') { p++; skip_ws_comments(p, e); if (p >= e || *p == ')') return; } else return;
        skip_value(p, e);
    }
}

inline int64_t parse_int(const char*& p, const char* e) {
    skip_ws_comments(p, e); bool neg = false;
    if (p < e && *p == '-') { neg = true; p++; }
    uint64_t v = 0; int d = 0;
    while (p < e && *p >= '0' && *p <= '9') { v = v * 10 + (*p - '0'); p++; d++; }
    if (d == 0) throw Error("invalid number");
    return neg ? -(int64_t)v : (int64_t)v;
}

inline double parse_float(const char*& p, const char* e) {
    skip_ws_comments(p, e); const char* st = p;
    if (p < e && *p == '-') p++;
    while (p < e && *p >= '0' && *p <= '9') p++;
    if (p < e && *p == '.') { p++; while (p < e && *p >= '0' && *p <= '9') p++; }
    if (p == st) throw Error("invalid number");
    double v; auto [ptr, ec] = std::from_chars(st, p, v);
    if (ec != std::errc()) throw Error("invalid float");
    return v;
}

inline bool parse_bool(const char*& p, const char* e) {
    skip_ws_comments(p, e);
    if (p + 4 <= e && p[0] == 't' && p[1] == 'r' && p[2] == 'u' && p[3] == 'e') { p += 4; return true; }
    if (p + 5 <= e && p[0] == 'f' && p[1] == 'a' && p[2] == 'l' && p[3] == 's' && p[4] == 'e') { p += 5; return false; }
    throw Error("invalid bool");
}

// ---- Pretty format ----
inline std::vector<int> build_match_table(const std::string& src) {
    int n = (int)src.size(); std::vector<int> mat(n, -1); std::vector<int> stk; bool inq = false;
    for (int i = 0; i < n; i++) {
        if (inq) { if (src[i] == '\\' && i + 1 < n) { i++; continue; } if (src[i] == '"') inq = false; continue; }
        switch (src[i]) {
            case '"': inq = true; break;
            case '{': case '(': case '[': stk.push_back(i); break;
            case '}': case ')': case ']': if (!stk.empty()) { int j = stk.back(); stk.pop_back(); mat[j] = i; mat[i] = j; } break;
        }
    }
    return mat;
}

inline void pretty_inline(std::string& out, const std::string& src, int start, int end) {
    int d = 0; bool inq = false;
    for (int i = start; i < end; i++) {
        char ch = src[i];
        if (inq) { out.push_back(ch); if (ch == '\\' && i + 1 < end) { i++; out.push_back(src[i]); } else if (ch == '"') inq = false; continue; }
        switch (ch) {
            case '"': inq = true; out.push_back(ch); break;
            case '{': case '(': case '[': d++; out.push_back(ch); break;
            case '}': case ')': case ']': d--; out.push_back(ch); break;
            case ',': out.push_back(','); if (d == 1) out.push_back(' '); break;
            default: out.push_back(ch); break;
        }
    }
}

std::string pretty_format(const std::string& compact);

// ---- Binary helpers ----
inline void bin_write_u32(std::string& buf, uint32_t v) { buf.append((const char*)&v, 4); }
inline uint32_t bin_read_u32(const char*& p) { uint32_t v; memcpy(&v, p, 4); p += 4; return v; }

} // namespace asun_core
