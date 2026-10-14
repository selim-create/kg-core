# Production deployment

The repository deploys `main` automatically to the KG Core WordPress plugin directory.

## One-time SSH setup on the development Mac

Create a dedicated key without a passphrase:

```bash
ssh-keygen -t ed25519 -C "kidsgourmet-github-deploy" -f "$HOME/.ssh/kidsgourmet_deploy" -N ""
```

Add a friendly SSH alias:

```sshconfig
Host kidsgourmet-prod
  HostName 72.60.82.157
  Port 65002
  User u399146576_hmrIbQ2w9
  IdentityFile ~/.ssh/kidsgourmet_deploy
  IdentitiesOnly yes
```

Install the public key on the server. This command asks for the current SSH password once:

```bash
cat "$HOME/.ssh/kidsgourmet_deploy.pub" | \
  ssh -p 65002 u399146576_hmrIbQ2w9@72.60.82.157 \
  'umask 077; mkdir -p ~/.ssh; cat >> ~/.ssh/authorized_keys'
```

Verify passwordless access:

```bash
ssh kidsgourmet-prod 'echo "SSH OK"'
```

## GitHub Actions secret

Create the repository Actions secret `KG_DEPLOY_SSH_KEY` with the full content of:

```bash
cat "$HOME/.ssh/kidsgourmet_deploy"
```

Do not commit the private key to Git.

## Production target

The workflow deploys to:

```text
/home/u399146576/websites/hmrIbQ2w9/public_html/wp-content/plugins/kg-core
```

Before merging the deployment PR, verify this exact directory exists:

```bash
ssh kidsgourmet-prod \
  'test -d /home/u399146576/websites/hmrIbQ2w9/public_html/wp-content/plugins/kg-core && echo "DEPLOY PATH OK"'
```

## How deployment works

- push/merge to `main`
- GitHub Actions checks out the repository
- files are synchronized over SSH with `rsync --delete`
- development-only directories are excluded
- Composer production dependencies are installed when Composer is available
- PHP syntax is checked remotely after deployment

The workflow intentionally does not deploy the web or mobile repositories. The AI runtime lives in `kg-core`; the mobile application requires its own app-store/EAS release process, and the web repository should keep its existing hosting integration.
