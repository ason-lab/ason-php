dnl config.m4 for extension ason

PHP_ARG_ENABLE([ason],
  [whether to enable ason support],
  [AS_HELP_STRING([--enable-ason],
    [Enable ason support])],
  [no])

if test "$PHP_ASON" != "no"; then
  dnl Check for C++ compiler
  PHP_REQUIRE_CXX()

  dnl Add C++17 flags and SIMD optimization
  CXXFLAGS="$CXXFLAGS -std=c++17 -O3 -march=native -msse2 -mavx2 -DNDEBUG"

  PHP_ADD_LIBRARY(stdc++, 1, ASON_SHARED_LIBADD)
  PHP_SUBST(ASON_SHARED_LIBADD)

  PHP_NEW_EXTENSION(ason, ason_php.cpp, $ext_shared,, -std=c++17 -O3 -march=native -msse2 -mavx2 -DNDEBUG)

  PHP_ADD_MAKEFILE_FRAGMENT
fi
