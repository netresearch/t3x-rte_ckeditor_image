# DDEV Services Documentation

This DDEV setup includes additional services for TYPO3 extension development.

## Included Services

### 1. Redis (Caching)

**Container**: `ddev-rte-ckeditor-image-redis`
**Image**: `redis:7-alpine`
**Port**: 6379 (internal)

**Purpose**: High-performance caching for TYPO3

**Access**:
```bash
# From host
ddev redis-cli

# From web container
ddev ssh
redis-cli -h redis
```

**Configuration**:
- See `.ddev/config.redis.yaml` for TYPO3 configuration example
- Add to `/var/www/html/v13/config/system/additional.php`

**Testing**:
```bash
ddev ssh
redis-cli -h redis ping
# Should return: PONG
```

---

### 2. MailPit (Email Testing)

**Container**: `ddev-rte-ckeditor-image-mailpit`
**Image**: `axllent/mailpit`
**Ports**:
- 1025 (SMTP)
- 8025 (Web UI)

**Purpose**: Catch all emails sent by TYPO3 for testing

**Access**:
- **Web UI**: `http://rte-ckeditor-image.ddev.site:8025`
- **SMTP**: `mailpit:1025` (from containers)

**TYPO3 Configuration**:
Already configured in `.ddev/docker-compose.web.yaml`:
```yaml
TYPO3_INSTALL_MAIL_TRANSPORT: smtp
TYPO3_INSTALL_MAIL_TRANSPORT_SMTP_SERVER: mailpit:1025
```

Or manually in `AdditionalConfiguration.php`:
```php
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] = 'smtp';
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_smtp_server'] = 'mailpit:1025';
```

**Testing**:
```bash
# Send test email from TYPO3
ddev ssh
cd /var/www/html/v13
vendor/bin/typo3 mailer:spool:send

# View in MailPit UI
open http://rte-ckeditor-image.ddev.site:8025
```

---

### 3. Ofelia (Cron/Scheduler)

**Container**: `ddev-rte-ckeditor-image-ofelia`
**Image**: `ghcr.io/netresearch/ofelia:latest`

**Purpose**: Run TYPO3 scheduler tasks automatically

**Configuration**: `.ddev/compose.ofelia.yaml`

**Scheduled Jobs**:
- TYPO3 scheduler for v11, v12, v13: Every 1 minute
- Cache warmup for v13: Every 1 hour

**View Logs**:
```bash
# Check if Ofelia is running
docker ps | grep ofelia

# View Ofelia logs
docker logs -f ddev-rte-ckeditor-image-ofelia

# Check scheduler execution
ddev ssh
cd /var/www/html/v13
vendor/bin/typo3 scheduler:list
```

**Manual Execution**:
```bash
ddev ssh
t3-scheduler-v13    # alias for scheduler:run on v13
t3-scheduler-all    # run scheduler on all versions
```

---

## Alternative: Traditional Cron in Web Container

If you prefer traditional cron instead of Ofelia:

1. **Enable cron in Dockerfile**:

   Edit `.ddev/web-build/Dockerfile` and add:
   ```dockerfile
   RUN apt-get update && apt-get install -y cron
   COPY install-cron.sh /opt/install-cron.sh
   RUN chmod +x /opt/install-cron.sh && /opt/install-cron.sh
   ```

2. **Restart DDEV**:
   ```bash
   ddev restart
   ```

3. **Verify cron**:
   ```bash
   ddev ssh
   crontab -l
   service cron status
   ```

---

## Service Management

### Start/Stop Services

```bash
# Restart all services
ddev restart

# Stop DDEV (keeps volumes)
ddev stop

# Remove containers (keeps volumes)
ddev delete

# Remove everything including volumes
ddev delete --omit-snapshot --yes
docker volume rm rte-ckeditor-image-redis-data
```

### View Service Status

```bash
# All DDEV containers
ddev describe

# All containers
docker ps | grep rte-ckeditor-image

# Service logs
docker logs ddev-rte-ckeditor-image-redis
docker logs ddev-rte-ckeditor-image-mailpit
docker logs ddev-rte-ckeditor-image-ofelia
```

### Access Services

```bash
# Redis CLI
ddev redis-cli

# Or from web container
ddev ssh
redis-cli -h redis

# MailPit web interface
open http://rte-ckeditor-image.ddev.site:8025

# Check Redis connection from TYPO3
ddev ssh
cd /var/www/html/v13
vendor/bin/typo3 cache:flush
```

---

## TYPO3 Scheduler Configuration

### Enable Scheduler Tasks

1. **Access TYPO3 Backend**: https://v13.rte-ckeditor-image.ddev.site/typo3/
2. **Login**: admin / Password:joh316
3. **System → Scheduler**
4. **Create tasks** (examples):
   - Table garbage collection
   - Index queue worker
   - File abstraction layer indexing
   - Import/Export tasks

### Verify Scheduler is Running

```bash
ddev ssh
cd /var/www/html/v13

# List all scheduler tasks
vendor/bin/typo3 scheduler:list

# Run manually (for testing)
vendor/bin/typo3 scheduler:run

# Check last execution time
vendor/bin/typo3 scheduler:list --verbose
```

---

## Performance Tuning

### Redis

**Memory Limit**: Currently 256MB
**Eviction Policy**: `allkeys-lru` (Least Recently Used)

To adjust:
```yaml
# .ddev/compose.services.yaml
environment:
  - REDIS_MAXMEMORY=512mb  # Increase if needed
```

### MailPit

No tuning needed for development. All emails are stored in memory.

### Ofelia/Cron

**Frequency**: Default is every 1 minute
To adjust, edit `.ddev/compose.ofelia.yaml`:

```yaml
# Every 5 minutes instead
ofelia.job-exec.typo3-scheduler-v13.schedule: "@every 5m"

# Specific time (e.g., 2am daily)
ofelia.job-exec.cache-warmup.schedule: "0 2 * * *"
```

---

## Troubleshooting

### Redis Not Connecting

```bash
# Test Redis
ddev ssh
redis-cli -h redis ping

# Should return: PONG
# If not, check Redis container
docker logs ddev-rte-ckeditor-image-redis
```

### MailPit Not Receiving Emails

```bash
# Check TYPO3 mail configuration
ddev ssh
cd /var/www/html/v13
vendor/bin/typo3 configuration:show MAIL

# Test email sending
vendor/bin/typo3 mailer:spool:send
```

### Scheduler Not Running

```bash
# Check Ofelia logs
docker logs -f ddev-rte-ckeditor-image-ofelia

# Manually run scheduler
ddev ssh
t3-scheduler-v13

# Check for errors
cd /var/www/html/v13
vendor/bin/typo3 scheduler:list --verbose
```

### Remove Service

To remove a service, comment it out in `.ddev/compose.services.yaml` and restart:

```bash
ddev restart
```

---

## Additional Services (Optional)

### Adminer (Database GUI)

```bash
ddev get ddev/ddev-adminer
ddev restart
# Access: https://rte-ckeditor-image.ddev.site:9999
```

### Elasticsearch

```yaml
# Add to .ddev/compose.services.yaml
elasticsearch:
  image: elasticsearch:8.10.2
  environment:
    - discovery.type=single-node
    - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
  ports:
    - "9200"
```

### Solr

```yaml
# Add to .ddev/compose.services.yaml
solr:
  image: solr:9
  ports:
    - "8983"
  volumes:
    - solr-data:/var/solr
```

---

## Quick Reference

| Service | Access | Purpose |
|---------|--------|---------|
| Redis | `redis-cli -h redis` | Caching |
| MailPit UI | http://localhost:8025 | Email testing |
| MailPit SMTP | `mailpit:1025` | Email delivery |
| Ofelia | Background | Cron jobs |
| Web | https://*.ddev.site | TYPO3 instances |
| Database | `ddev mysql` | MariaDB |

---

**Questions?** Check DDEV docs: https://ddev.readthedocs.io/
