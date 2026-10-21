using System.ComponentModel.DataAnnotations;

namespace DeploymentDashboard.Models;

public enum ChecklistStatus
{
    Planned = 0,
    InProgress = 1,
    Done = 2
}

/// <summary>
/// A single item on the deployment checklist. Kept deliberately small so that the
/// application stays readable, but it exercises input validation, state changes and
/// data binding in Razor Pages.
/// </summary>
public sealed class DeploymentTask
{
    public Guid Id { get; set; } = Guid.NewGuid();

    [Required(ErrorMessage = "A title is required.")]
    [StringLength(80, MinimumLength = 3, ErrorMessage = "Title must be 3-80 characters.")]
    public string Title { get; set; } = string.Empty;

    [StringLength(240, ErrorMessage = "Notes cannot exceed 240 characters.")]
    public string? Notes { get; set; }

    public ChecklistStatus Status { get; set; } = ChecklistStatus.Planned;

    public string Owner { get; set; } = "Azure App Service";

    public DateTimeOffset CreatedUtc { get; set; } = DateTimeOffset.UtcNow;

    public DateTimeOffset? CompletedUtc { get; set; }
}
