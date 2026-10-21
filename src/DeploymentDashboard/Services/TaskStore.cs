using System.Collections.Concurrent;
using DeploymentDashboard.Models;

namespace DeploymentDashboard.Services;

public interface ITaskStore
{
    IReadOnlyList<DeploymentTask> All();
    DeploymentTask Add(string title, string? notes, string owner);
    bool Advance(Guid id);
    bool Remove(Guid id);
}

/// <summary>
/// Thread safe, process local store. Data is seeded with the Task 3 checklist and is
/// reset whenever the App Service recycles, which keeps the demo stateless and safe
/// to host on a shared App Service plan.
/// </summary>
public sealed class InMemoryTaskStore : ITaskStore
{
    private readonly ConcurrentDictionary<Guid, DeploymentTask> _tasks = new();

    public InMemoryTaskStore()
    {
        Seed("Create Azure account and resource group", "Azure for Students subscription activated.", ChecklistStatus.Done);
        Seed("Install Visual Studio 2022 with the ASP.NET workload", "17.14.x, .NET 9 SDK.", ChecklistStatus.Done);
        Seed("Develop the C# web application", "ASP.NET Core Razor Pages, .NET 9.", ChecklistStatus.Done);
        Seed("Publish to Azure App Service", "Right click > Publish > Azure App Service (Windows).", ChecklistStatus.Done);
        Seed("Verify the public URL", "HTTPS endpoint returns HTTP 200 with deployment telemetry.", ChecklistStatus.InProgress);
        Seed("Install PHP and build the PHP web app", "PHP 8.3 + Composer, deployed as a second App Service.", ChecklistStatus.Planned);
        Seed("Deactivate (stop) both App Services", "Capture the 'Stopped' status as evidence.", ChecklistStatus.Planned);
    }

    private void Seed(string title, string notes, ChecklistStatus status)
    {
        var task = new DeploymentTask
        {
            Title = title,
            Notes = notes,
            Status = status,
            Owner = "Student",
            CompletedUtc = status == ChecklistStatus.Done ? DateTimeOffset.UtcNow : null
        };
        _tasks[task.Id] = task;
    }

    public IReadOnlyList<DeploymentTask> All() =>
        _tasks.Values
              .OrderBy(t => t.Status)
              .ThenByDescending(t => t.CreatedUtc)
              .ToList();

    public DeploymentTask Add(string title, string? notes, string owner)
    {
        var task = new DeploymentTask
        {
            Title = title.Trim(),
            Notes = string.IsNullOrWhiteSpace(notes) ? null : notes.Trim(),
            Owner = string.IsNullOrWhiteSpace(owner) ? "Student" : owner.Trim()
        };
        _tasks[task.Id] = task;
        return task;
    }

    public bool Advance(Guid id)
    {
        if (!_tasks.TryGetValue(id, out var task)) return false;

        task.Status = task.Status switch
        {
            ChecklistStatus.Planned => ChecklistStatus.InProgress,
            ChecklistStatus.InProgress => ChecklistStatus.Done,
            _ => ChecklistStatus.Planned
        };
        task.CompletedUtc = task.Status == ChecklistStatus.Done ? DateTimeOffset.UtcNow : null;
        return true;
    }

    public bool Remove(Guid id) => _tasks.TryRemove(id, out _);
}
