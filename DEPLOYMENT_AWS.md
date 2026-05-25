# AWS Deployment Guide

Team size: 10-20 internal users.

## Infrastructure

- **Database**: AWS RDS MySQL (`t3.medium`).
- **Compute**: AWS ECS Fargate or EC2 (2 vCPU, 4GB RAM).

## Environment Configuration

```env
DB_CONNECTION=mysql
QUEUE_CONNECTION=database
```

## Optimization

### PHP-FPM
```ini
pm = static
pm.max_children = 60
```

### Nginx / ALB
```nginx
proxy_read_timeout 300;
fastcgi_read_timeout 300;
```

### Queue Workers
```bash
php artisan queue:work --processes=8 --tries=3 --timeout=600
```
