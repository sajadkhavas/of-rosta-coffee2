from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    file = Path(path)
    source = file.read_text()
    if old not in source:
        raise RuntimeError(f"Expected pattern missing in {path}: {old[:180]!r}")
    file.write_text(source.replace(old, new))


# Use the real session guard login path when switching identities. Unlike
# actingAs(), login() writes the guard identifier into the session; the explicit
# password hash then matches Sanctum AuthenticateSession's production contract.
replace(
    "backend/tests/Support/AuthenticatesRecordedSession.php",
    '''        $this->actingAs($user, 'web');
        $guard = Auth::guard('web');
        $passwordHash = method_exists($guard, 'hashPasswordForCookie')
            ? $guard->hashPasswordForCookie($user->getAuthPassword())
            : $user->getAuthPassword();

        $this->withSession([
''',
    '''        $this->flushSession();
        $guard = Auth::guard('web');
        $guard->login($user);
        $passwordHash = method_exists($guard, 'hashPasswordForCookie')
            ? $guard->hashPasswordForCookie($user->getAuthPassword())
            : $user->getAuthPassword();

        $this->withSession([
''',
)

# content_hash is the optimistic-concurrency token for the editable document,
# so changing title/SEO/visibility must rotate it even when body blocks stay the
# same. Keep the existing normalized body hash as the insert placeholder, then
# replace it with a stable hash of every editable persisted field.
service = "backend/app/Services/Content/ContentWriteService.php"
replace(
    service,
    '''            $entry = ContentEntry::query()->create([
                ...$this->normalizedData($data, $body, true),
                'status' => ContentStatus::Draft->value,
            ]);

            $this->syncRelations($entry, $relations);
''',
    '''            $entry = ContentEntry::query()->create([
                ...$this->normalizedData($data, $body, true),
                'status' => ContentStatus::Draft->value,
            ]);
            $entry->forceFill([
                'content_hash' => $this->documentHash($entry),
            ])->save();

            $this->syncRelations($entry, $relations);
''',
)
replace(
    service,
    '''            if ($wasPublished) {
                $locked->status = ContentStatus::Review;
                $locked->published_at = null;
            }
            $locked->save();
''',
    '''            if ($wasPublished) {
                $locked->status = ContentStatus::Review;
                $locked->published_at = null;
            }
            $locked->content_hash = $this->documentHash($locked);
            $locked->save();
''',
)
insert_before = '''    /**
     * @param list<array<string, mixed>> $relations
     */
    private function syncRelations(ContentEntry $entry, array $relations): void
'''
document_hash = '''    private function documentHash(ContentEntry $entry): string
    {
        $attributes = $entry->getAttributes();
        $document = [];
        foreach ([
            'author_id',
            'type',
            'title',
            'slug',
            'canonical_path',
            'excerpt',
            'body',
            'seo_title',
            'seo_description',
            'robots_index',
            'robots_follow',
            'og_title',
            'og_description',
            'og_media_url',
            'schema_type',
            'keywords',
        ] as $field) {
            $document[$field] = $attributes[$field] ?? null;
        }

        return hash('sha256', json_encode(
            $document,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

'''
replace(service, insert_before, document_hash + insert_before)
