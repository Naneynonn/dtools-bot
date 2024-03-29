const shardCount = 2;

module.exports = {
  apps: Array.from({ length: shardCount }, (_, i) => {
    const appName = `dtools${i}`;
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
      out_file: `./logs/${appName}-out.log`,
      error_file: `./logs/${appName}-error.log`,
      min_uptime: 1000,
      restart_delay: 2000,
      max_restarts: 10,
      env: {
        NODE_ENV: "development",
      },
      env_production: {
        NODE_ENV: "production",
      },
    };
  }),
};
