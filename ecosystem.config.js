const shardCount = 2;

module.exports = {
  apps: Array.from({ length: shardCount }, (_, i) => {
    const paddedIndex = String(i).padStart(2, '0');
    const appName = `dtools${paddedIndex}`;
    const namespace = `fenrir`;

    return {
      name: appName,
      namespace: namespace,
      script: "index.php",
      interpreter: "php",
      max_memory_restart: "200M",
      args: `${i} ${shardCount}`,
      watch: false,
      log_date_format: "YYYY-MM-DD HH:mm:ss",
      out_file: `./logs/pm2/${appName}-out.log`,
      error_file: `./logs/pm2/${appName}-error.log`,
      min_uptime: 1000,
      max_restarts: 10,
      exp_backoff_restart_delay: 100,
      env: {
        NODE_ENV: "development",
      },
      env_production: {
        NODE_ENV: "production",
      },
    };
  }),
};
