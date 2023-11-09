module.exports = {
  apps: [
    {
      name: "dtools0-fnrr",
      script: "index.php",
      interpreter: "php",
      max_memory_restart: "200M",
      args: "0 2",
      watch: false,
      log_date_format: "YYYY-MM-DD HH:mm:ss",
      out_file: "./logs/dtools0-out.log",
      error_file: "./logs/dtools0-error.log",
      min_uptime: 1000,
      restart_delay: 2000,
      max_restarts: 10,
      env: {
        NODE_ENV: "development",
      },
      env_production: {
        NODE_ENV: "production",
      },
    },
    {
      name: "dtools1-fnrr",
      script: "index.php",
      interpreter: "php",
      max_memory_restart: "200M",
      args: "1 2",
      watch: false,
      log_date_format: "YYYY-MM-DD HH:mm:ss",
      out_file: "./logs/dtools1-out.log",
      error_file: "./logs/dtools1-error.log",
      min_uptime: 1000,
      restart_delay: 2000,
      max_restarts: 10,
      env: {
        NODE_ENV: "development",
      },
      env_production: {
        NODE_ENV: "production",
      },
    },
  ],
}
