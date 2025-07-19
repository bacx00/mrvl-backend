// you could also add:
{
  name: 'mrvl-backend',
  cwd: '/var/www/mrvl-backend',
  script: '/usr/bin/php',
  args: 'artisan serve --host=127.0.0.1 --port=8000',
  env: {
    APP_ENV: 'production',
    APP_URL: 'https://1039tfjgievqa983.mrvl.net'
  },
  exec_mode: 'fork',
  instances: 1
}
