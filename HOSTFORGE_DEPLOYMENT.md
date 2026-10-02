# HostForge Deployment — Node Capacity Fix

## Reported error

> No eligible node is available to schedule this workload. The allocated node
> "Singapore Node 1" is at its configured max_containers ceiling (252/250).

## Root cause

This is a **HostForge scheduler/node-capacity error**, not a PHP, Apache, routing,
database, or Dockerfile application error.

`252/250` means the HostForge node is already above its configured container
ceiling. The application image cannot raise that node-level limit from inside
the container.

The project was scanned for:

- `max_containers`
- node/scheduler configuration
- `Singapore Node 1`
- Docker Compose/Swarm/Kubernetes scheduling settings
- application code that could force a node assignment

No project-level setting controlling that HostForge node ceiling was found.

## Required HostForge-side fix

Before redeploying, an administrator with HostForge node/scheduler access must
do **one** of the following:

1. Increase the configured `max_containers` value for `Singapore Node 1` so that
   it is greater than the current container count plus this deployment; **or**
2. Stop/remove unused containers on that node until it is below its configured
   ceiling; **or**
3. Assign/redeploy this workload to another node that has available container
   capacity.

After capacity is available, redeploy the existing image.

## Important

Do **not** add a fake `max_containers` value to the Dockerfile, PHP files, or
application configuration. That setting belongs to the HostForge scheduler,
and an unsupported setting would not change the node's actual capacity.

## Application-side scan

The Dockerfile is a normal single-container Apache/PHP image. It exposes port
80 and does not request multiple replicas or define a node constraint.

The application also does not contain a `max_containers` setting or a
`Singapore Node 1` dependency.

## Deployment checklist

- [ ] `Singapore Node 1` has available container capacity.
- [ ] The HostForge workload has no stale/unused containers consuming the node
      quota.
- [ ] Redeploy after capacity is available.
- [ ] Confirm the container reaches healthy/running state.
- [ ] Open `/health.php` and verify the HTTP health check responds successfully.
- [ ] Verify the application's database environment variables are configured
      in HostForge.
- [ ] Verify `GEMINI_API_KEY` only if the AI System Assistant is required.
- [ ] Verify Gmail SMTP environment variables only if Gmail OTP is required.

## Result of this source scan

No source-code change can directly resolve a HostForge `252/250` node ceiling.
The correct fix is to free capacity, raise the node limit, or select a node with
capacity, then redeploy this image.
