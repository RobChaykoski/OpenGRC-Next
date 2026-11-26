#!/bin/sh
# Container liveness probe.
#
# The login page returns 200 only when the app, database, and compiled assets
# are all working; `/` returns a redirect either way.
#
# Probe over loopback but present the Host header from APP_URL. Laravel's
# TrustHosts middleware rejects a bare `localhost` request with a 400 on any
# deployment whose APP_URL is a real host or IP, which marked healthy
# containers unhealthy and blocked the worker from starting.
set -eu

base="${APP_URL:-http://localhost}"
authority="${base#*://}"
authority="${authority%%/*}"

exec curl -fsS -H "Host: ${authority:-localhost}" http://127.0.0.1/app/login >/dev/null
