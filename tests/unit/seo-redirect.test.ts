import { describe, expect, test } from "bun:test";
import { resolveSeoRedirect } from "../../src/lib/seo-redirect";

function apiResponse(redirect: Record<string, unknown> | null): Response {
  return Response.json({ data: { redirect } });
}

describe("SSR SEO redirects", () => {
  test("returns only a permanent same-origin redirect", async () => {
    const response = await resolveSeoRedirect(
      new Request("https://rosta.shop/old-guide"),
      async () =>
        apiResponse({
          destination_path: "/guides/new-guide",
          status_code: 301,
          is_active: true,
        }),
    );

    expect(response?.status).toBe(301);
    expect(response?.headers.get("location")).toBe("https://rosta.shop/guides/new-guide");
  });

  test("accepts 308 but rejects unsafe, temporary and self redirects", async () => {
    const permanent = await resolveSeoRedirect(new Request("https://rosta.shop/old"), async () =>
      apiResponse({
        destination_path: "/new",
        status_code: 308,
        is_active: true,
      }),
    );
    expect(permanent?.status).toBe(308);

    for (const redirect of [
      { destination_path: "https://example.com", status_code: 301, is_active: true },
      { destination_path: "//example.com/path", status_code: 301, is_active: true },
      { destination_path: "/old", status_code: 301, is_active: true },
      { destination_path: "/new", status_code: 302, is_active: true },
      { destination_path: "/new", status_code: 301, is_active: false },
    ]) {
      expect(
        await resolveSeoRedirect(new Request("https://rosta.shop/old"), async () =>
          apiResponse(redirect),
        ),
      ).toBeNull();
    }
  });

  test("fails open to the original 404 when the API is unavailable or malformed", async () => {
    expect(
      await resolveSeoRedirect(new Request("https://rosta.shop/missing"), async () => {
        throw new Error("offline");
      }),
    ).toBeNull();
    expect(
      await resolveSeoRedirect(
        new Request("https://rosta.shop/missing"),
        async () => new Response("bad json"),
      ),
    ).toBeNull();
    expect(
      await resolveSeoRedirect(
        new Request("https://rosta.shop/missing", { method: "POST" }),
        async () => apiResponse(null),
      ),
    ).toBeNull();
  });
});
