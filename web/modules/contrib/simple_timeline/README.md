# A Simple Timeline

The Simple Timeline module allows the rendering of entities selected by a view
on a simple vertical timeline.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/simple_timeline).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/simple_timeline).

## Table of contents

- Requirements
- Installation
- Configuration
- Customization
- Maintainers

## Requirements

This module requires no modules outside of Drupal core.

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

Create a new view and choose the style plugin Simple Timeline.
Settings provided by the views style plugin timeline are :

- Position of rows
  - you can set the position of rows related to the timeline : on the left, on the
      right or alternated.
- Position of the marker
    - You can set the marker's vertical position : top, center or bottom of each row.


## Customization

To customize the timeline and/or marker look, you can override some simple css
rules in your theme.

For example.

The timeline color.

    ul.timeline-list:after {
      background-color: #555555;
    }

And the marker color and form.

    ul.timeline-list li.timeline-item .timeline-item-wrapper span.timeline-marker {
      background: #fff;
      border: 3px solid #555555;
      border-radius: 0;
    }

## Alternatives

- [Timeline block](https://www.drupal.org/project/timeline_block)

  Timeline Block lets you enter timelines from your drupal block configuration
  area. It is a customizable drupal timeline module. Using this timelines module
  lets you easily add timelines on your drupal website.

## Maintainers

- Alan Lobo - [alansaviolobo](https://www.drupal.org/u/alansaviolobo)
- Guido Schmitz - [Guido_S](https://www.drupal.org/u/guido_s)
- [Chandra Gowsalya Kannan](https://www.drupal.org/u/chandra-gowsalya-kannan)
- Flocon de toile - [flocondetoile](https://www.drupal.org/u/flocondetoile)
