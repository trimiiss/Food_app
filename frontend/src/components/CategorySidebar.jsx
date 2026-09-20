/**
 * Category navigation for the menu.
 *
 * A sidebar rather than a row of chips: the menu has 9 categories, which
 * overflowed a single row, and a vertical list leaves room for the count and
 * stays readable as more categories are added. On narrow screens it collapses
 * into a horizontal scroller, where a tall list would push the food off-screen.
 */
export default function CategorySidebar({ categories = [], activeSlug, onSelect, totalCount }) {
  return (
    <aside className="category-sidebar">
      <h2 className="category-sidebar-title">Categories</h2>
      <nav aria-label="Menu categories">
        <ul>
          <li>
            <button
              type="button"
              className={`category-link ${activeSlug === '' ? 'active' : ''}`}
              aria-current={activeSlug === '' ? 'true' : undefined}
              onClick={() => onSelect('')}
            >
              <span>All dishes</span>
              {totalCount != null && <span className="category-count">{totalCount}</span>}
            </button>
          </li>
          {categories.map((category) => (
            <li key={category.id}>
              <button
                type="button"
                className={`category-link ${activeSlug === category.slug ? 'active' : ''}`}
                aria-current={activeSlug === category.slug ? 'true' : undefined}
                onClick={() => onSelect(category.slug)}
              >
                <span>{category.name}</span>
                <span className="category-count">{category.products_count}</span>
              </button>
            </li>
          ))}
        </ul>
      </nav>
    </aside>
  )
}
