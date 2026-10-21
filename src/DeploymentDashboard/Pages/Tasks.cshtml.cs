using DeploymentDashboard.Models;
using DeploymentDashboard.Services;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.RazorPages;

namespace DeploymentDashboard.Pages;

public class TasksModel : PageModel
{
    private readonly ITaskStore _store;

    public TasksModel(ITaskStore store) => _store = store;

    public IReadOnlyList<DeploymentTask> Tasks { get; private set; } = Array.Empty<DeploymentTask>();

    public string? Message { get; private set; }

    [BindProperty]
    public TaskInput Input { get; set; } = new();

    public sealed class TaskInput
    {
        [System.ComponentModel.DataAnnotations.Required(ErrorMessage = "A title is required.")]
        [System.ComponentModel.DataAnnotations.StringLength(80, MinimumLength = 3,
            ErrorMessage = "Title must be 3-80 characters.")]
        public string Title { get; set; } = string.Empty;

        [System.ComponentModel.DataAnnotations.StringLength(240, ErrorMessage = "Notes cannot exceed 240 characters.")]
        public string? Notes { get; set; }

        [System.ComponentModel.DataAnnotations.StringLength(60)]
        public string Owner { get; set; } = "Student";
    }

    public void OnGet() => Load();

    public IActionResult OnPostAdd()
    {
        if (!ModelState.IsValid)
        {
            Load();
            return Page();
        }

        var created = _store.Add(Input.Title, Input.Notes, Input.Owner);
        TempData["Message"] = $"Added \"{created.Title}\".";
        return RedirectToPage();
    }

    public IActionResult OnPostAdvance(Guid id)
    {
        Message = _store.Advance(id)
            ? "Task status updated."
            : "That task no longer exists.";
        Load();
        return Page();
    }

    public IActionResult OnPostRemove(Guid id)
    {
        Message = _store.Remove(id) ? "Task removed." : "That task no longer exists.";
        Load();
        return Page();
    }

    private void Load()
    {
        Tasks = _store.All();
        Message ??= TempData["Message"] as string;
    }
}
