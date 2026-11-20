#!/usr/bin/env bash
# Resolve the mechanical part of a merge of upstream/main into the fork.
#
# The fork changes four files that upstream also changes:
#   README.md                      replaced by the Chinese readme
#   app/Http/Kernel.php            registers LocalizeResponse last in the web group
#   docker/production/Dockerfile   copies resources/localization into the image
#   phpunit.xml                    sets LOCALIZE_OFF for the test suite
#
# A conflict in one of them is expected. This script keeps the fork's readme,
# writes upstream's readme to README.upstream.md, and re-applies the fork's lines
# where the merge dropped them. A conflict in any other file is a real upstream
# change, so the merge is aborted and the job fails for a human to look at.
#
# Run from the merge root while a merge is in progress:
#   git merge --no-edit --no-commit upstream/main || true
#   .github/scripts/reconcile-upstream-merge.sh upstream/main
#   git commit --no-edit
set -euo pipefail

upstream_ref=${1:-upstream/main}

readme=README.md
upstream_readme=README.upstream.md
kernel=app/Http/Kernel.php
dockerfile=docker/production/Dockerfile
phpunit=phpunit.xml
localization_copy='COPY --chown=www-data:www-data resources/localization ./resources/localization'
localization_env='    <env name="LOCALIZE_OFF" value="true" force="true"/>'
# The fork registry patch: these files are resolved by taking upstream's version
# and re-applying the stored patch, so an upstream edit next to it keeps merging.
registry_files=" app/Actions/Server/UpdateCoolify.php app/Livewire/Settings/Updates.php resources/views/livewire/settings/updates.blade.php "
registry_patch=.github/scripts/fork-upgrade-registry.patch

say() {
    printf '%s\n' "$*"
}

die() {
    if [ -n "${GITHUB_ACTIONS:-}" ]; then
        printf '::error::%s\n' "$*"
    else
        printf 'error: %s\n' "$*" >&2
    fi
    exit 1
}

is_conflicted() {
    git diff --name-only --diff-filter=U | grep -qxF -- "$1"
}

git rev-parse -q --verify MERGE_HEAD >/dev/null || die "no merge in progress: start the merge before running this script"

# Snapshot the conflicts: resolving one of them removes it from the unmerged list.
conflicts=$(git diff --name-only --diff-filter=U)

# Fail on a conflict the fork has no rule for, before touching anything.
for file in $conflicts; do
    case " $readme $kernel $dockerfile $phpunit $registry_files " in
        *" $file "*) ;;
        *)
            git merge --abort
            die "upstream changed $file and the fork has no rule for it; merge aborted"
            ;;
    esac
done

# Keep the Chinese readme and refresh the upstream copy.
if is_conflicted "$readme"; then
    git checkout --ours -- "$readme"
    say "$readme: kept the fork's Chinese readme"
fi
git show "$upstream_ref:$readme" >"$upstream_readme"
say "$upstream_readme: refreshed from $upstream_ref"

# Kernel: take upstream's file on conflict, then restore both fork lines.
if is_conflicted "$kernel"; then
    git checkout --theirs -- "$kernel"
    say "$kernel: took the upstream version"
fi

if ! grep -qF 'use App\Http\Middleware\LocalizeResponse;' "$kernel"; then
    tmp=$(mktemp)
    if ! awk '
        BEGIN { target = "use App\\Http\\Middleware\\LocalizeResponse;"; placed = 0; count = 0 }
        { lines[NR] = $0 }
        $0 ~ /^use App\\Http\\Middleware\\/ {
            middleware[++count] = NR
            if (!placed && $0 > target) {
                position = NR
                placed = 1
            }
        }
        END {
            if (!placed) {
                position = count > 0 ? middleware[count] + 1 : 2
            }
            if (position > NR + 1) {
                position = NR + 1
            }
            for (i = 1; i <= NR; i++) {
                if (i == position) {
                    print target
                }
                print lines[i]
            }
        }
    ' "$kernel" >"$tmp"; then
        rm -f "$tmp"
        die "could not add the LocalizeResponse import to $kernel"
    fi
    mv "$tmp" "$kernel"
fi

# The registration has to stay last in the web group, so drop ours wherever it is
# and add it back just before the group closes. This is idempotent.
tmp=$(mktemp)
if ! awk '
    BEGIN { in_web = 0; found = 0 }
    /^[[:space:]]*.web.[[:space:]]*=>[[:space:]]*\[/ {
        in_web = 1
        print
        next
    }
    in_web && /^[[:space:]]*\],/ {
        print "            // Keep last: it rewrites the finished response body."
        print "            LocalizeResponse::class,"
        found = 1
        in_web = 0
        print
        next
    }
    {
        if ($0 ~ /LocalizeResponse::class,/) {
            next
        }
        if ($0 ~ /Keep last: it rewrites the finished response body\./) {
            next
        }
        print
    }
    END { if (!found) exit 3 }
' "$kernel" >"$tmp"; then
    rm -f "$tmp"
    die "could not find the web middleware group in $kernel"
fi
mv "$tmp" "$kernel"
say "$kernel: refreshed the LocalizeResponse registration"

# Dockerfile: take upstream's file on conflict, then restore the COPY line.
if is_conflicted "$dockerfile"; then
    git checkout --theirs -- "$dockerfile"
    say "$dockerfile: took the upstream version"
fi

if ! grep -qF "$localization_copy" "$dockerfile"; then
    tmp=$(mktemp)
    if ! awk -v line="$localization_copy" '
        BEGIN { placed = 0 }
        { print }
        !placed && /resources\/image-scan/ {
            print line
            placed = 1
        }
        END { if (!placed) exit 4 }
    ' "$dockerfile" >"$tmp"; then
        rm -f "$tmp"
        die "could not add the resources/localization COPY line to $dockerfile"
    fi
    mv "$tmp" "$dockerfile"
    say "$dockerfile: re-added the resources/localization COPY line"
fi

# phpunit.xml: take upstream's file on conflict, then restore the env line.
if is_conflicted "$phpunit"; then
    git checkout --theirs -- "$phpunit"
    say "$phpunit: took the upstream version"
fi

if ! grep -qF 'name="LOCALIZE_OFF"' "$phpunit"; then
    tmp=$(mktemp)
    if ! awk -v line="$localization_env" '
        BEGIN { placed = 0 }
        { print }
        !placed && /name="DB_CONNECTION"/ {
            print line
            placed = 1
        }
        END { if (!placed) exit 5 }
    ' "$phpunit" >"$tmp"; then
        rm -f "$tmp"
        die "could not add the LOCALIZE_OFF env line to $phpunit"
    fi
    mv "$tmp" "$phpunit"
    say "$phpunit: re-added the LOCALIZE_OFF env line"
fi

# Registry patch files: take upstream's version and re-apply the stored patch on
# top. A clean 3-way apply keeps merging when upstream edited another hunk; if
# upstream changed the same lines, the apply fails and the merge is aborted.
for file in $conflicts; do
    case "$registry_files" in
        *" $file "*)
            git checkout --theirs -- "$file"
            git add -- "$file"
            say "$file: took the upstream version"
            ;;
    esac
done

for file in $conflicts; do
    case "$registry_files" in
        *" $file "*)
            if ! git apply --3way --include="$file" "$registry_patch"; then
                git merge --abort
                die "could not re-apply the fork patch to $file; merge aborted"
            fi
            say "$file: re-applied the fork patch"
            ;;
    esac
done

# The merge is only clean when every fork marker is back.
[ -f app/Http/Middleware/LocalizeResponse.php ] || die "app/Http/Middleware/LocalizeResponse.php is missing"
[ -f bootstrap/helpers/fork.php ] || die "bootstrap/helpers/fork.php is missing"
[ -d resources/localization/zh-cn ] || die "resources/localization/zh-cn is missing"
grep -qF '# Coolify 中文版' "$readme" || die "$readme no longer holds the Chinese readme"
grep -qF 'use App\Http\Middleware\LocalizeResponse;' "$kernel" || die "$kernel lost the LocalizeResponse import"
grep -qF "$localization_copy" "$dockerfile" || die "$dockerfile lost the resources/localization COPY line"
grep -qF 'name="LOCALIZE_OFF"' "$phpunit" || die "$phpunit lost the LOCALIZE_OFF env line"

# The fork's images live under a registry path, so REGISTRY_URL has to win over the
# docker.io/ghcr.io setting. These files keep the fork registry in the upgrade path.
[ "$(grep -c 'function coolifyUpgradeRegistryUrl' bootstrap/helpers/fork.php)" -eq 1 ] || die "bootstrap/helpers/fork.php lost coolifyUpgradeRegistryUrl()"
grep -qF 'coolifyUpgradeRegistryUrl()' app/Actions/Server/UpdateCoolify.php || die "app/Actions/Server/UpdateCoolify.php no longer upgrades from the configured registry"
grep -qF 'coolifyUpgradeRegistryUrl()' app/Livewire/Settings/Updates.php || die "app/Livewire/Settings/Updates.php no longer shows the configured registry"
grep -qF 'coolifyUpgradeRegistryUrl()' resources/views/livewire/settings/updates.blade.php || die "resources/views/livewire/settings/updates.blade.php no longer offers the configured registry"

git add -- "$readme" "$upstream_readme" "$kernel" "$dockerfile" "$phpunit" \
    app/Actions/Server/UpdateCoolify.php app/Livewire/Settings/Updates.php \
    resources/views/livewire/settings/updates.blade.php
say "every fork marker is in place; the merge is ready to commit"
