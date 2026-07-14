const INSTAGRAM_GRID_SELECTOR = "[data-instagram-grid]";
const INSTAGRAM_POST_SELECTOR = "[data-instagram-post]";
const DEFAULT_LIMIT = 3;

function getPostTime(post) {
  const value = post.dataset.publishedAt || "";
  const time = new Date(value).getTime();

  return Number.isNaN(time) ? 0 : time;
}

export function initInstagramLatestPosts() {
  const grids = document.querySelectorAll(INSTAGRAM_GRID_SELECTOR);

  grids.forEach((grid) => {
    const limit = Number.parseInt(grid.dataset.instagramLimit || "", 10) || DEFAULT_LIMIT;
    const posts = Array.from(grid.querySelectorAll(INSTAGRAM_POST_SELECTOR));

    posts
      .map((post, index) => ({
        post,
        index,
        time: getPostTime(post)
      }))
      .sort((a, b) => b.time - a.time || a.index - b.index)
      .forEach(({ post }, index) => {
        post.hidden = index >= limit;
        grid.appendChild(post);
      });
  });
}
