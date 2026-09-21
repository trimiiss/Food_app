/**
 * Category navigation for the menu.
 *
 * A sidebar rather than a row of chips: the menu has a dozen categories, which
 * overflowed a single row, and a vertical list stays readable as more
 * categories are added. On narrow screens it collapses into a horizontal
 * scroller, where a tall list would push the food off-screen.
 */
export default function CategorySidebar({ categories = [], activeSlug, onSelect }) {
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
              All dishes
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
                {category.name}
              </button>
            </li>
          ))}
        </ul>
      </nav>
    </aside>
  )
}
