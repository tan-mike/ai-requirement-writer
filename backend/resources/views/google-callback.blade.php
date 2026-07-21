<!DOCTYPE html>
<html>
<head><title>Authenticating...</title></head>
<body>
<script>
  (function() {
    var payload = @json($payload);
    var origin = '{{ config("app.frontend_url") }}';

    if (window.opener) {
      window.opener.postMessage(payload, origin);
      window.close();
    } else {
      if (payload.token) {
        window.location.replace(origin + '/auth/callback#token=' +
          encodeURIComponent(payload.token) + '&user=' +
          encodeURIComponent(JSON.stringify(payload.user)));
      } else {
        window.location.replace(origin + '/login?error=' +
          encodeURIComponent(payload.error || 'Authentication failed'));
      }
    }
  })();
</script>
</body>
</html>
